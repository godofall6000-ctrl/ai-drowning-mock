"""
Flask application for AI Drowning Detection System.
Provides web interface and REST API for model inference.
"""

import os
from flask import Flask, request, jsonify, render_template, send_from_directory
from flask_cors import CORS
from werkzeug.utils import secure_filename
import cv2
import numpy as np
from pathlib import Path
import base64
import io
from PIL import Image

from ..config import get_config
from ..models import BasicCNN, ResNetModel, EfficientNetModel, MobileNetModel, EnsembleModel
from ..utils.logging_utils import get_logger, setup_logging

logger = get_logger(__name__)


class ModelManager:
    """Manages model loading and inference."""

    def __init__(self):
        self.config = get_config()
        self.models = {}
        self.current_model = None
        self._load_models()

    def _load_models(self):
        """Load available trained models."""
        models_dir = Path("models")

        if not models_dir.exists():
            logger.warning("Models directory not found")
            return

        # Try to load different model types
        model_configs = [
            ("basic_cnn", BasicCNN, "drowning_cnn.h5"),
            ("resnet", ResNetModel, "resnet_drowning.h5"),
            ("efficientnet", EfficientNetModel, "efficientnet_drowning.h5"),
            ("mobilenet", MobileNetModel, "mobilenet_drowning.h5"),
            ("ensemble", EnsembleModel, "ensemble_config.json"),
        ]

        for model_name, model_class, filename in model_configs:
            model_path = models_dir / filename
            if model_path.exists():
                try:
                    model = model_class(config=self.config)
                    if model_name != "ensemble":
                        model.load_model(str(model_path))
                    else:
                        model.load_ensemble(str(models_dir / "ensemble"))

                    self.models[model_name] = model
                    logger.info(f"Loaded {model_name} model")
                except Exception as e:
                    logger.error(f"Failed to load {model_name}: {e}")

        # Set default model
        if self.models:
            self.current_model = list(self.models.keys())[0]
            logger.info(f"Default model set to: {self.current_model}")

    def predict(self, image_data, model_name=None):
        """
        Make prediction on image data.

        Args:
            image_data: Preprocessed image array
            model_name: Name of model to use

        Returns:
            Prediction results
        """
        if model_name is None:
            model_name = self.current_model

        if model_name not in self.models:
            raise ValueError(f"Model {model_name} not available")

        model = self.models[model_name]

        # Make prediction
        predictions = model.predict(image_data)
        predicted_class = model.predict_classes(image_data)[0]
        confidence = float(predictions[0][predicted_class])

        # Class names
        class_names = ['normal', 'drowning']

        return {
            'prediction': class_names[predicted_class],
            'confidence': confidence,
            'probabilities': {
                class_names[i]: float(prob)
                for i, prob in enumerate(predictions[0])
            },
            'model_used': model_name
        }

    def get_available_models(self):
        """Get list of available models."""
        return list(self.models.keys())


# Global model manager
model_manager = ModelManager()


def create_app(config_name=None):
    """
    Create and configure Flask application.

    Args:
        config_name: Configuration environment name

    Returns:
        Flask application instance
    """
    app = Flask(__name__,
                template_folder='../../templates',
                static_folder='../../static')

    # Enable CORS
    CORS(app)

    # Configure app
    config = get_config()
    app.config['MAX_CONTENT_LENGTH'] = config.api.max_content_length
    app.config['UPLOAD_FOLDER'] = config.api.upload_folder

    # Ensure upload folder exists
    upload_path = Path(app.config['UPLOAD_FOLDER'])
    upload_path.mkdir(exist_ok=True)

    # Set up logging
    setup_logging(config)

    # Register blueprints
    from .routes import api_bp
    app.register_blueprint(api_bp, url_prefix='/api')

    # Health check endpoint
    @app.route('/health')
    def health():
        """Health check endpoint."""
        return jsonify({
            'status': 'healthy',
            'models_loaded': len(model_manager.models),
            'available_models': model_manager.get_available_models()
        })

    # Main web interface
    @app.route('/')
    def index():
        """Main web interface."""
        return render_template('index.html',
                             models=model_manager.get_available_models(),
                             current_model=model_manager.current_model)

    # Static file serving
    @app.route('/static/<path:filename>')
    def static_files(filename):
        return send_from_directory('../../static', filename)

    logger.info("Flask application created")
    return app


def preprocess_image(image_file):
    """
    Preprocess uploaded image for model inference.

    Args:
        image_file: File-like object containing image

    Returns:
        Preprocessed image array
    """
    # Read image
    image = Image.open(image_file)

    # Convert to RGB if necessary
    if image.mode != 'RGB':
        image = image.convert('RGB')

    # Resize to model input size
    config = get_config()
    target_size = config.data.input_shape[:2]
    image = image.resize(target_size, Image.Resampling.LANCZOS)

    # Convert to numpy array
    image_array = np.array(image)

    # Normalize
    image_array = image_array.astype(np.float32) / 255.0

    # Add batch dimension
    image_array = np.expand_dims(image_array, axis=0)

    return image_array


def allowed_file(filename):
    """Check if file extension is allowed."""
    config = get_config()
    return '.' in filename and \
           filename.rsplit('.', 1)[1].lower() in config.api.allowed_extensions


if __name__ == '__main__':
    app = create_app()
    config = get_config()

    logger.info(f"Starting Flask server on {config.api.host}:{config.api.port}")
    app.run(
        host=config.api.host,
        port=config.api.port,
        debug=config.api.debug
    )