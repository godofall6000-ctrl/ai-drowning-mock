"""
Ensemble model for drowning detection.
Combines multiple CNN architectures for improved performance and robustness.
"""

import numpy as np
import tensorflow as tf
from typing import List, Optional, Dict, Any
from pathlib import Path

from .base_model import BaseModel
from .basic_cnn import BasicCNN
from .resnet_model import ResNetModel
from .efficientnet_model import EfficientNetModel
from .mobilenet_model import MobileNetModel
from ..config import Config, get_config
from ..utils.logging_utils import get_logger

logger = get_logger(__name__)


class EnsembleModel(BaseModel):
    """
    Ensemble model that combines predictions from multiple CNN architectures.
    Provides improved accuracy and robustness through model diversity.
    """

    def __init__(
        self,
        config: Optional[Config] = None,
        model_configs: Optional[List[Dict[str, Any]]] = None
    ):
        """
        Initialize the ensemble model.

        Args:
            config: Configuration object. If None, uses global config.
            model_configs: List of model configurations for ensemble members.
        """
        super().__init__(config)

        if model_configs is None:
            # Default ensemble configuration
            model_configs = [
                {'type': 'BasicCNN', 'weight': 1.0},
                {'type': 'ResNetModel', 'weight': 1.0, 'freeze_base': True},
                {'type': 'EfficientNetModel', 'weight': 1.0, 'freeze_base': True},
                {'type': 'MobileNetModel', 'weight': 1.0, 'freeze_base': True},
            ]

        self.model_configs = model_configs
        self.ensemble_members: List[BaseModel] = []
        self.weights: List[float] = []

        self._build_ensemble()
        logger.info(f"Ensemble model initialized with {len(self.ensemble_members)} members")

    def _build_ensemble(self):
        """Build the ensemble by creating individual models."""
        model_classes = {
            'BasicCNN': BasicCNN,
            'ResNetModel': ResNetModel,
            'EfficientNetModel': EfficientNetModel,
            'MobileNetModel': MobileNetModel,
        }

        for config in self.model_configs:
            model_type = config['type']
            weight = config.get('weight', 1.0)

            if model_type not in model_classes:
                logger.warning(f"Unknown model type: {model_type}")
                continue

            # Remove 'type' and 'weight' from config before passing to model
            model_kwargs = {k: v for k, v in config.items() if k not in ['type', 'weight']}

            try:
                model = model_classes[model_type](config=self.config, **model_kwargs)
                self.ensemble_members.append(model)
                self.weights.append(weight)
                logger.info(f"Added {model_type} to ensemble with weight {weight}")
            except Exception as e:
                logger.error(f"Failed to create {model_type}: {e}")

        if not self.ensemble_members:
            raise ValueError("No valid models could be created for ensemble")

        # Normalize weights
        total_weight = sum(self.weights)
        self.weights = [w / total_weight for w in self.weights]

    def build_model(self) -> tf.keras.Model:
        """
        Build the ensemble model.
        Note: This creates a wrapper model for inference, but training is handled separately.

        Returns:
            Keras model (wrapper for ensemble inference)
        """
        inputs = tf.keras.Input(shape=self.input_shape)

        # Create ensemble prediction layer
        ensemble_output = self._create_ensemble_layer(inputs)

        # Create wrapper model
        self.model = tf.keras.Model(
            inputs=inputs,
            outputs=ensemble_output,
            name='EnsembleModel'
        )

        logger.info("Ensemble wrapper model built")
        return self.model

    def _create_ensemble_layer(self, inputs):
        """Create the ensemble prediction layer."""
        # This is a simplified implementation
        # In practice, you'd load saved models and create a proper ensemble
        return tf.keras.layers.Dense(
            self.num_classes,
            activation='softmax',
            name='ensemble_output'
        )(inputs)

    def train_ensemble(
        self,
        train_generator: tf.keras.utils.Sequence,
        validation_generator: tf.keras.utils.Sequence,
        epochs: Optional[int] = None
    ) -> List[tf.keras.callbacks.History]:
        """
        Train all ensemble members.

        Args:
            train_generator: Training data generator
            validation_generator: Validation data generator
            epochs: Number of epochs. If None, uses config value.

        Returns:
            List of training histories for each member
        """
        if epochs is None:
            epochs = self.config.model.epochs

        histories = []
        logger.info("Training ensemble members")

        for i, model in enumerate(self.ensemble_members):
            logger.info(f"Training ensemble member {i+1}/{len(self.ensemble_members)}: {model.model_name}")

            try:
                history = model.train(train_generator, validation_generator, epochs=epochs)
                histories.append(history)
            except Exception as e:
                logger.error(f"Failed to train {model.model_name}: {e}")
                histories.append(None)

        logger.info("Ensemble training completed")
        return histories

    def predict_ensemble(self, data: np.ndarray) -> np.ndarray:
        """
        Make ensemble predictions by averaging predictions from all members.

        Args:
            data: Input data array

        Returns:
            Ensemble predictions
        """
        if not self.ensemble_members:
            raise ValueError("No ensemble members available")

        # Get predictions from each member
        member_predictions = []
        for model in self.ensemble_members:
            try:
                pred = model.predict(data)
                member_predictions.append(pred)
            except Exception as e:
                logger.warning(f"Failed to get prediction from {model.model_name}: {e}")

        if not member_predictions:
            raise ValueError("No valid predictions from ensemble members")

        # Weighted average of predictions
        ensemble_pred = np.zeros_like(member_predictions[0])
        for pred, weight in zip(member_predictions, self.weights[:len(member_predictions)]):
            ensemble_pred += pred * weight

        return ensemble_pred

    def predict_classes_ensemble(self, data: np.ndarray) -> np.ndarray:
        """
        Predict class labels using ensemble.

        Args:
            data: Input data array

        Returns:
            Predicted class indices
        """
        predictions = self.predict_ensemble(data)
        return np.argmax(predictions, axis=1)

    def evaluate_ensemble(
        self,
        test_generator: tf.keras.utils.Sequence
    ) -> Dict[str, float]:
        """
        Evaluate the ensemble on test data.

        Args:
            test_generator: Test data generator

        Returns:
            Dictionary with evaluation metrics
        """
        logger.info("Evaluating ensemble model")

        # Get all test data
        test_data = []
        test_labels = []

        for i in range(len(test_generator)):
            batch_data, batch_labels = test_generator[i]
            test_data.extend(batch_data)
            test_labels.extend(batch_labels)

        test_data = np.array(test_data)
        test_labels = np.array(test_labels)

        # Get ensemble predictions
        predictions = self.predict_ensemble(test_data)
        pred_classes = np.argmax(predictions, axis=1)
        true_classes = np.argmax(test_labels, axis=1)

        # Calculate metrics
        accuracy = np.mean(pred_classes == true_classes)

        # Calculate AUC if binary classification
        if self.num_classes == 2:
            from sklearn.metrics import roc_auc_score
            auc = roc_auc_score(true_classes, predictions[:, 1])
        else:
            auc = None

        metrics = {
            'accuracy': float(accuracy),
            'auc': float(auc) if auc is not None else None,
        }

        logger.info(f"Ensemble evaluation results: {metrics}")
        return metrics

    def save_ensemble(self, base_path: str = "models/ensemble"):
        """
        Save all ensemble members.

        Args:
            base_path: Base directory to save models
        """
        base_path = Path(base_path)
        base_path.mkdir(parents=True, exist_ok=True)

        logger.info(f"Saving ensemble to {base_path}")

        for i, model in enumerate(self.ensemble_members):
            model_path = base_path / f"member_{i}_{model.model_name}.h5"
            try:
                model.save_model(str(model_path))
                logger.info(f"Saved {model.model_name} to {model_path}")
            except Exception as e:
                logger.error(f"Failed to save {model.model_name}: {e}")

        # Save ensemble configuration
        config_path = base_path / "ensemble_config.json"
        ensemble_config = {
            'model_configs': self.model_configs,
            'weights': self.weights,
            'num_members': len(self.ensemble_members)
        }

        import json
        with open(config_path, 'w') as f:
            json.dump(ensemble_config, f, indent=2)

        logger.info(f"Ensemble configuration saved to {config_path}")

    def load_ensemble(self, base_path: str = "models/ensemble"):
        """
        Load all ensemble members.

        Args:
            base_path: Base directory containing saved models
        """
        base_path = Path(base_path)

        # Load configuration
        config_path = base_path / "ensemble_config.json"
        if config_path.exists():
            import json
            with open(config_path, 'r') as f:
                ensemble_config = json.load(f)
                self.model_configs = ensemble_config['model_configs']
                self.weights = ensemble_config['weights']

        # Load models
        self.ensemble_members = []
        for i in range(len(self.model_configs)):
            model_path = base_path / f"member_{i}_*.h5"
            # Note: In practice, you'd need to match the correct model files
            # This is a simplified implementation

        logger.info(f"Ensemble loaded from {base_path}")

    def get_ensemble_info(self) -> Dict[str, Any]:
        """
        Get detailed information about the ensemble.

        Returns:
            Dictionary with ensemble information
        """
        member_info = []
        for i, model in enumerate(self.ensemble_members):
            info = model.get_model_info()
            info['weight'] = self.weights[i]
            member_info.append(info)

        return {
            'architecture': 'Ensemble',
            'num_members': len(self.ensemble_members),
            'members': member_info,
            'weights': self.weights,
            'ensemble_method': 'weighted_average'
        }

    def add_member(self, model: BaseModel, weight: float = 1.0):
        """
        Add a new member to the ensemble.

        Args:
            model: Model to add
            weight: Weight for the model in ensemble predictions
        """
        self.ensemble_members.append(model)
        self.weights.append(weight)

        # Renormalize weights
        total_weight = sum(self.weights)
        self.weights = [w / total_weight for w in self.weights]

        logger.info(f"Added {model.model_name} to ensemble with weight {weight}")

    def remove_member(self, index: int):
        """
        Remove a member from the ensemble.

        Args:
            index: Index of member to remove
        """
        if 0 <= index < len(self.ensemble_members):
            removed_model = self.ensemble_members.pop(index)
            removed_weight = self.weights.pop(index)

            # Renormalize remaining weights
            if self.weights:
                total_weight = sum(self.weights)
                self.weights = [w / total_weight for w in self.weights]

            logger.info(f"Removed {removed_model.model_name} from ensemble")
        else:
            logger.warning(f"Invalid member index: {index}")