"""
ResNet architecture for drowning detection.
Implements ResNet50 with transfer learning capabilities.
"""

import tensorflow as tf
from typing import Optional, Tuple

from .base_model import BaseModel
from ..config import Config, get_config
from ..utils.logging_utils import get_logger

logger = get_logger(__name__)


class ResNetModel(BaseModel):
    """
    ResNet50-based model for drowning detection.
    Uses transfer learning with fine-tuning capabilities.
    """

    def __init__(
        self,
        config: Optional[Config] = None,
        include_top: bool = True,
        weights: str = 'imagenet',
        freeze_base: bool = True
    ):
        """
        Initialize the ResNet model.

        Args:
            config: Configuration object. If None, uses global config.
            include_top: Whether to include the classification head.
            weights: Pre-trained weights to use ('imagenet' or None).
            freeze_base: Whether to freeze the base model layers.
        """
        self.include_top = include_top
        self.weights = weights
        self.freeze_base = freeze_base
        super().__init__(config)
        logger.info("ResNetModel initialized")

    def build_model(self) -> tf.keras.Model:
        """
        Build the ResNet50 architecture with custom classification head.

        Returns:
            Compiled Keras model
        """
        # Load ResNet50 base model
        base_model = tf.keras.applications.ResNet50(
            include_top=False,
            weights=self.weights,
            input_shape=self.input_shape,
            pooling='avg'
        )

        # Freeze base model layers if specified
        if self.freeze_base:
            for layer in base_model.layers:
                layer.trainable = False
            logger.info("Base ResNet50 layers frozen")
        else:
            logger.info("Base ResNet50 layers trainable")

        inputs = tf.keras.Input(shape=self.input_shape)

        # Pass through base model
        x = base_model(inputs, training=not self.freeze_base)

        # Custom classification head
        x = tf.keras.layers.Dense(1024, activation='relu')(x)
        x = tf.keras.layers.BatchNormalization()(x)
        x = tf.keras.layers.Dropout(self.config.model.dropout_rate)(x)

        x = tf.keras.layers.Dense(512, activation='relu')(x)
        x = tf.keras.layers.BatchNormalization()(x)
        x = tf.keras.layers.Dropout(self.config.model.dropout_rate)(x)

        # Output layer
        outputs = tf.keras.layers.Dense(
            self.num_classes,
            activation='softmax',
            name='classification_output'
        )(x)

        # Create model
        self.model = tf.keras.Model(inputs=inputs, outputs=outputs, name='ResNet50')

        # Compile model
        self.compile_model()

        logger.info("ResNet50 model built successfully")
        return self.model

    def unfreeze_layers(self, num_layers: int = 10):
        """
        Unfreeze the last N layers of the base model for fine-tuning.

        Args:
            num_layers: Number of layers to unfreeze from the end.
        """
        if self.model is None:
            logger.warning("Model not built yet. Call build_model() first.")
            return

        # Find the base model within the layers
        base_model = None
        for layer in self.model.layers:
            if isinstance(layer, tf.keras.Model):
                base_model = layer
                break

        if base_model is None:
            logger.warning("Could not find base model in layers")
            return

        # Unfreeze the last N layers
        for layer in base_model.layers[-num_layers:]:
            layer.trainable = True

        logger.info(f"Unfroze last {num_layers} layers of base model")

        # Recompile model with new trainable layers
        self.compile_model()

    def get_model_info(self) -> dict:
        """
        Get detailed information about the model.

        Returns:
            Dictionary with model information
        """
        info = super().get_model_info()
        info.update({
            'architecture': 'ResNet50',
            'base_model': 'ResNet50',
            'weights': self.weights,
            'freeze_base': self.freeze_base,
            'include_top': self.include_top,
            'dense_units': [1024, 512],
            'use_batch_norm': True,
            'use_dropout': True,
            'transfer_learning': True
        })
        return info

    def fine_tune(
        self,
        train_generator: tf.keras.utils.Sequence,
        validation_generator: tf.keras.utils.Sequence,
        initial_epochs: int = 10,
        fine_tune_epochs: int = 20,
        unfreeze_layers: int = 10
    ) -> tf.keras.callbacks.History:
        """
        Perform fine-tuning training with progressive unfreezing.

        Args:
            train_generator: Training data generator
            validation_generator: Validation data generator
            initial_epochs: Number of epochs with frozen base
            fine_tune_epochs: Number of epochs with unfrozen layers
            unfreeze_layers: Number of layers to unfreeze

        Returns:
            Training history
        """
        logger.info("Starting fine-tuning process")

        # Initial training with frozen base
        logger.info(f"Phase 1: Training with frozen base for {initial_epochs} epochs")
        history1 = self.train(train_generator, validation_generator, epochs=initial_epochs)

        # Unfreeze layers for fine-tuning
        self.unfreeze_layers(unfreeze_layers)

        # Fine-tuning with lower learning rate
        original_lr = self.config.model.learning_rate
        self.config.model.learning_rate = original_lr * 0.1  # Reduce LR for fine-tuning
        self.compile_model()

        logger.info(f"Phase 2: Fine-tuning for {fine_tune_epochs} epochs")
        history2 = self.train(train_generator, validation_generator, epochs=fine_tune_epochs)

        # Restore original learning rate
        self.config.model.learning_rate = original_lr

        # Combine histories
        combined_history = {}
        for key in history1.history.keys():
            combined_history[key] = history1.history[key] + history2.history[key]

        logger.info("Fine-tuning completed")
        return combined_history