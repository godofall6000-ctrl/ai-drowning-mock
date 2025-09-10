# Multi-stage Docker build for AI Drowning Detection System

# Base stage with Python and system dependencies
FROM python:3.9-slim as base

# Set environment variables
ENV PYTHONUNBUFFERED=1 \
    PYTHONDONTWRITEBYTECODE=1 \
    PIP_NO_CACHE_DIR=1 \
    PIP_DISABLE_PIP_VERSION_CHECK=1

# Install system dependencies
RUN apt-get update && apt-get install -y \
    build-essential \
    libglib2.0-0 \
    libsm6 \
    libxext6 \
    libxrender-dev \
    libgomp1 \
    libgtk2.0-dev \
    pkg-config \
    && rm -rf /var/lib/apt/lists/*

# Create non-root user
RUN useradd --create-home --shell /bin/bash app \
    && mkdir -p /app \
    && chown -R app:app /app

USER app
WORKDIR /app

# Copy requirements and install Python dependencies
COPY --chown=app:app requirements.txt pyproject.toml ./
RUN pip install --user --upgrade pip \
    && pip install --user -r requirements.txt

# Development stage
FROM base as development

# Install additional dev dependencies
RUN pip install --user \
    pytest \
    pytest-cov \
    black \
    isort \
    flake8 \
    mypy

# Copy source code
COPY --chown=app:app src/ ./src/
COPY --chown=app:app templates/ ./templates/
COPY --chown=app:app static/ ./static/

# Set environment
ENV PYTHONPATH=/app/src:$PYTHONPATH
ENV FLASK_ENV=development

EXPOSE 5000

CMD ["python", "-m", "src.api.app"]

# Production stage
FROM base as production

# Copy source code
COPY --chown=app:app src/ ./src/
COPY --chown=app:app templates/ ./templates/
COPY --chown=app:app static/ ./static/

# Copy pre-trained models (if available)
COPY --chown=app:app models/ ./models/

# Set environment
ENV PYTHONPATH=/app/src:$PYTHONPATH
ENV FLASK_ENV=production

EXPOSE 5000

# Health check
HEALTHCHECK --interval=30s --timeout=10s --start-period=5s --retries=3 \
    CMD curl -f http://localhost:5000/health || exit 1

CMD ["python", "-m", "src.api.app"]

# Training stage
FROM base as training

# Install additional training dependencies
RUN pip install --user \
    mlflow \
    wandb \
    tensorboard

# Copy training code
COPY --chown=app:app src/ ./src/
COPY --chown=app:app data/ ./data/

# Set environment
ENV PYTHONPATH=/app/src:$PYTHONPATH

# Default command for training
CMD ["python", "-m", "src.train_pipeline"]

# API-only stage (lightweight)
FROM python:3.9-slim as api-only

# Install minimal dependencies
RUN apt-get update && apt-get install -y \
    libglib2.0-0 \
    libsm6 \
    libxext6 \
    libgomp1 \
    && rm -rf /var/lib/apt/lists/*

RUN useradd --create-home --shell /bin/bash app \
    && mkdir -p /app \
    && chown -R app:app /app

USER app
WORKDIR /app

# Copy only API-related files
COPY --chown=app:app requirements.txt ./
RUN pip install --user --upgrade pip \
    && pip install --user -r requirements.txt flask-cors

COPY --chown=app:app src/api/ ./src/api/
COPY --chown=app:app src/config.py ./src/
COPY --chown=app:app src/utils/ ./src/utils/
COPY --chown=app:app src/models/ ./src/models/
COPY --chown=app:app templates/ ./templates/
COPY --chown=app:app static/ ./static/
COPY --chown=app:app models/ ./models/

ENV PYTHONPATH=/app/src:$PYTHONPATH
ENV FLASK_ENV=production

EXPOSE 5000

HEALTHCHECK --interval=30s --timeout=10s --start-period=5s --retries=3 \
    CMD python -c "import requests; requests.get('http://localhost:5000/health')"

CMD ["python", "-m", "src.api.app"]