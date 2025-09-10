"""
REST API for AI Drowning Detection System.
Provides web interface and API endpoints for model inference.
"""

from .app import create_app
from .routes import api_bp

__all__ = ["create_app", "api_bp"]