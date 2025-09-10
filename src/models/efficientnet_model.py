"""
EfficientNet architecture for drowning detection.
Implements EfficientNetB0 with transfer learning and advanced features.
"""

import tensorflow as tf
from typing import Optional

from .base_model import BaseModel
from ..config import Config, get_config
from ..utils.logging_utils import get_logger

logger = get_logger(__name__)


class EfficientNetModel(BaseModel):
    """
    EfficientNet-based model for drowning detection.
    Uses EfficientNetB0 with compound scaling for optimal performance.
    """

    def __init__(
        self,
        config: Optional[Config] = None,
        model_version: str = 'B0',
        include_top: bool = True,
        weights: str = 'imagenet',
        freeze_base: bool = True
    ):
        """
        Initialize the EfficientNet model.

        Args:
            config: Configuration object. If None, uses global config.
            model_version: EfficientNet version ('B0', 'B1', 'B2', etc.).
            include_top: Whether to include the classification head.
            weights: Pre-trained weights to use ('imagenet' or None).
            freeze_base: Whether to freeze the base model layers.
        """
        self.model_version = model_version
        self.include_top = include_top
        self.weights = weights
        self.freeze_base = freeze_base
        super().__init__(config)
        logger.info(f"EfficientNet{model_version} model initialized")

    def build_model(self) -> tf.keras.Model:
        """
        Build the EfficientNet architecture with custom classification head.

        Returns:
            Compiled Keras model
        """
        # Select EfficientNet model based on version
        efficientnet_models = {
            'B0': tf.keras.applications.EfficientNetB0,
            'B1': tf.keras.applications.EfficientNetB1,
            'B2': tf.keras.applications.EfficientNetB2,
            'B3': tf.keras.applications.EfficientNetB3,
            'B4': tf.keras.applications.EfficientNetB4,
            'B5': tf.keras.applications.EfficientNetB5,
            'B6': tf.keras.applications.EfficientNetB6,
            'B7': tf.keras.applications.EfficientNetB7,
        }

        if self.model_version not in efficientnet_models:
            raise ValueError(f"Unsupported EfficientNet version: {self.model_version}")

        efficientnet_class = efficientnet_models[self.model_version]

        # Load EfficientNet base model
        base_model = efficientnet_class(
            include_top=False,
            weights=self.weights,
            input_shape=self.input_shape,
            pooling='avg'
        )

        # Freeze base model layers if specified
        if self.freeze_base:
            for layer in base_model.layers:
                layer.trainable = False
            logger.info(f"Base EfficientNet{self.model_version} layers frozen")
        else:
            logger.info(f"Base EfficientNet{self.model_version} layers trainable")

        inputs = tf.keras.Input(shape=self.input_shape)

        # Pass through base model
        x = base_model(inputs, training=not self.freeze_base)

        # Custom classification head with advanced features
        x = tf.keras.layers.Dense(640, activation='swish')(x)  # Swish activation
        x = tf.keras.layers.BatchNormalization()(x)
        x = tf.keras.layers.Dropout(self.config.model.dropout_rate)(x)

        x = tf.keras.layers.Dense(320, activation='swish')(x)
        x = tf.keras.layers.BatchNormalization()(x)
        x = tf.keras.layers.Dropout(self.config.model.dropout_rate)(x)

        # Squeeze-and-Excitation block for attention
        x = self._squeeze_excitation_block(x, ratio=16)

        # Output layer
        outputs = tf.keras.layers.Dense(
            self.num_classes,
            activation='softmax',
            name='classification_output'
        )(x)

        # Create model
        self.model = tf.keras.Model(
            inputs=inputs,
            outputs=outputs,
            name=f'EfficientNet{self.model_version}'
        )

        # Compile model
        self.compile_model()

        logger.info(f"EfficientNet{self.model_version} model built successfully")
        return self.model

    def _squeeze_excitation_block(self, input_tensor, ratio=16):
        """
        Squeeze-and-Excitation block for channel attention.

        Args:
            input_tensor: Input tensor
            ratio: Reduction ratio for squeeze operation

        Returns:
            Output tensor with attention applied
        """
        channels = input_tensor.shape[-1]

        # Squeeze
        x = tf.keras.layers.GlobalAveragePooling1D()(input_tensor)
        x = tf.keras.layers.Reshape((1, channels))(x)

        # Excitation
        x = tf.keras.layers.Dense(channels // ratio, activation='relu')(x)
        x = tf.keras.layers.Dense(channels, activation='sigmoid')(x)

        # Scale
        x = tf.keras.layers.Multiply()([input_tensor, x])

        return x

    def unfreeze_layers(self, num_layers: int = 20):
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
            'architecture': f'EfficientNet{self.model_version}',
            'base_model': f'EfficientNet{self.model_version}',
            'weights': self.weights,
            'freeze_base': self.freeze_base,
            'include_top': self.include_top,
            'dense_units': [640, 320],
            'use_batch_norm': True,
            'use_dropout': True,
            'use_se_block': True,
            'activation': 'swish',
            'transfer_learning': True,
            'compound_scaling': True
        })
        return info

    def progressive_unfreezing(
        self,
        train_generator: tf.keras.utils.Sequence,
        validation_generator: tf.keras.utils.Sequence,
        stages: list = None
    ) -> list:
        """
        Perform progressive unfreezing training.

        Args:
            train_generator: Training data generator
            validation_generator: Validation data generator
            stages: List of (epochs, layers_to_unfreeze) tuples

        Returns:
            List of training histories for each stage
        """
        if stages is None:
            stages = [
                (5, 0),      # Stage 1: Train head only
                (10, 10),    # Stage 2: Unfreeze last 10 layers
                (15, 30),    # Stage 3: Unfreeze last 30 layers
                (20, 50),    # Stage 4: Unfreeze last 50 layers
            ]

        histories = []
        logger.info("Starting progressive unfreezing training")

        for i, (epochs, layers_to_unfreeze) in enumerate(stages):
            logger.info(f"Stage {i+1}: Training for {epochs} epochs, unfreezing {layers_to_unfreeze} layers")

            if layers_to_unfreeze > 0:
                self.unfreeze_layers(layers_to_unfreeze)

            # Reduce learning rate for later stages
            lr_multiplier = 0.1 ** i
            original_lr = self.config.model.learning_rate
            self.config.model.learning_rate = original_lr * lr_multiplier
            self.compile_model()

            history = self.train(train_generator, validation_generator, epochs=epochs)
            histories.append(history)

        # Restore original learning rate
        self.config.model.learning_rate = original_lr

        logger.info("Progressive unfreezing completed")
        return histories