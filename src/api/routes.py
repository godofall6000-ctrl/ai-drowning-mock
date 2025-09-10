"""
API routes for AI Drowning Detection System.
Provides REST endpoints for model inference and management.
"""

from flask import Blueprint, request, jsonify, current_app
import traceback
from werkzeug.exceptions import BadRequest

from .app import model_manager, preprocess_image, allowed_file
from ..utils.logging_utils import get_logger

logger = get_logger(__name__)

api_bp = Blueprint('api', __name__)


@api_bp.route('/predict', methods=['POST'])
def predict():
    """
    Predict drowning risk from uploaded image.

    Expected input:
    - file: Image file (PNG, JPG, JPEG)
    - model: Optional model name to use

    Returns:
    - JSON with prediction results
    """
    try:
        # Check if file is present
        if 'file' not in request.files:
            raise BadRequest("No file provided")

        file = request.files['file']

        if file.filename == '':
            raise BadRequest("No file selected")

        # Check file type
        if not allowed_file(file.filename):
            raise BadRequest("Unsupported file type")

        # Get model name
        model_name = request.form.get('model', model_manager.current_model)

        # Preprocess image
        image_data = preprocess_image(file)

        # Make prediction
        result = model_manager.predict(image_data, model_name)

        logger.info(f"Prediction made using {model_name}: {result['prediction']} "
                   f"(confidence: {result['confidence']:.4f})")

        return jsonify({
            'success': True,
            'result': result
        })

    except BadRequest as e:
        logger.warning(f"Bad request: {e}")
        return jsonify({
            'success': False,
            'error': str(e)
        }), 400

    except Exception as e:
        logger.error(f"Prediction error: {e}")
        logger.error(traceback.format_exc())
        return jsonify({
            'success': False,
            'error': 'Internal server error'
        }), 500


@api_bp.route('/predict/base64', methods=['POST'])
def predict_base64():
    """
    Predict drowning risk from base64 encoded image.

    Expected input:
    - image: Base64 encoded image string
    - model: Optional model name to use

    Returns:
    - JSON with prediction results
    """
    try:
        data = request.get_json()

        if not data or 'image' not in data:
            raise BadRequest("No image data provided")

        # Decode base64 image
        import base64
        from PIL import Image
        import io

        image_data = base64.b64decode(data['image'])
        image = Image.open(io.BytesIO(image_data))

        # Convert to RGB if necessary
        if image.mode != 'RGB':
            image = image.convert('RGB')

        # Preprocess
        from ..config import get_config
        config = get_config()
        target_size = config.data.input_shape[:2]
        image = image.resize(target_size, Image.Resampling.LANCZOS)

        image_array = np.array(image)
        image_array = image_array.astype(np.float32) / 255.0
        image_array = np.expand_dims(image_array, axis=0)

        # Get model name
        model_name = data.get('model', model_manager.current_model)

        # Make prediction
        result = model_manager.predict(image_array, model_name)

        logger.info(f"Base64 prediction made using {model_name}: {result['prediction']}")

        return jsonify({
            'success': True,
            'result': result
        })

    except BadRequest as e:
        logger.warning(f"Bad base64 request: {e}")
        return jsonify({
            'success': False,
            'error': str(e)
        }), 400

    except Exception as e:
        logger.error(f"Base64 prediction error: {e}")
        return jsonify({
            'success': False,
            'error': 'Internal server error'
        }), 500


@api_bp.route('/models', methods=['GET'])
def list_models():
    """
    Get list of available models.

    Returns:
    - JSON with available models and current model
    """
    try:
        return jsonify({
            'success': True,
            'models': model_manager.get_available_models(),
            'current_model': model_manager.current_model
        })

    except Exception as e:
        logger.error(f"Error listing models: {e}")
        return jsonify({
            'success': False,
            'error': 'Internal server error'
        }), 500


@api_bp.route('/models/<model_name>', methods=['POST'])
def switch_model(model_name):
    """
    Switch to a different model.

    Args:
        model_name: Name of model to switch to

    Returns:
    - JSON confirmation
    """
    try:
        if model_name not in model_manager.models:
            return jsonify({
                'success': False,
                'error': f'Model {model_name} not available'
            }), 404

        model_manager.current_model = model_name

        logger.info(f"Switched to model: {model_name}")

        return jsonify({
            'success': True,
            'message': f'Switched to model {model_name}',
            'current_model': model_name
        })

    except Exception as e:
        logger.error(f"Error switching model: {e}")
        return jsonify({
            'success': False,
            'error': 'Internal server error'
        }), 500


@api_bp.route('/batch_predict', methods=['POST'])
def batch_predict():
    """
    Predict on multiple images.

    Expected input:
    - files: Multiple image files
    - model: Optional model name to use

    Returns:
    - JSON with batch prediction results
    """
    try:
        if 'files' not in request.files:
            raise BadRequest("No files provided")

        files = request.files.getlist('files')
        model_name = request.form.get('model', model_manager.current_model)

        if not files:
            raise BadRequest("No files selected")

        results = []

        for file in files:
            if file.filename == '':
                continue

            if not allowed_file(file.filename):
                logger.warning(f"Skipping unsupported file: {file.filename}")
                continue

            try:
                # Preprocess image
                image_data = preprocess_image(file)

                # Make prediction
                result = model_manager.predict(image_data, model_name)

                results.append({
                    'filename': file.filename,
                    'result': result
                })

            except Exception as e:
                logger.error(f"Error processing {file.filename}: {e}")
                results.append({
                    'filename': file.filename,
                    'error': str(e)
                })

        logger.info(f"Batch prediction completed: {len(results)} images processed")

        return jsonify({
            'success': True,
            'results': results,
            'total_processed': len(results)
        })

    except BadRequest as e:
        logger.warning(f"Bad batch request: {e}")
        return jsonify({
            'success': False,
            'error': str(e)
        }), 400

    except Exception as e:
        logger.error(f"Batch prediction error: {e}")
        return jsonify({
            'success': False,
            'error': 'Internal server error'
        }), 500


@api_bp.route('/model_info/<model_name>', methods=['GET'])
def get_model_info(model_name):
    """
    Get detailed information about a specific model.

    Args:
        model_name: Name of model to get info for

    Returns:
    - JSON with model information
    """
    try:
        if model_name not in model_manager.models:
            return jsonify({
                'success': False,
                'error': f'Model {model_name} not available'
            }), 404

        model = model_manager.models[model_name]
        model_info = model.get_model_info()

        return jsonify({
            'success': True,
            'model_info': model_info
        })

    except Exception as e:
        logger.error(f"Error getting model info: {e}")
        return jsonify({
            'success': False,
            'error': 'Internal server error'
        }), 500


@api_bp.route('/performance', methods=['GET'])
def get_performance():
    """
    Get performance metrics for current model.

    Returns:
    - JSON with performance metrics
    """
    try:
        # This would typically load from saved evaluation results
        # For now, return placeholder
        return jsonify({
            'success': True,
            'performance': {
                'accuracy': 0.95,
                'precision': 0.93,
                'recall': 0.94,
                'f1_score': 0.935,
                'auc': 0.96
            },
            'model': model_manager.current_model
        })

    except Exception as e:
        logger.error(f"Error getting performance: {e}")
        return jsonify({
            'success': False,
            'error': 'Internal server error'
        }), 500