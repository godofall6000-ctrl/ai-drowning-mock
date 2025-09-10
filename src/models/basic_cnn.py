"""
Basic CNN architecture for drowning detection.
A custom convolutional neural network with multiple layers.
"""

import tensorflow as tf
from typing import Optional

from .base_model import BaseModel
from ..config import Config, get_config
from ..utils.logging_utils import get_logger

logger = get_logger(__name__)


class BasicCNN(BaseModel):
    """
    Basic CNN architecture with custom layers.
    Provides a lightweight yet effective model for drowning detection.
    """

    def __init__(self, config: Optional[Config] = None):
        """
        Initialize the Basic CNN model.

        Args:
            config: Configuration object. If None, uses global config.
        """
        super().__init__(config)
        logger.info("BasicCNN model initialized")

    def build_model(self) -> tf.keras.Model:
        """
        Build the Basic CNN architecture.

        Returns:
            Compiled Keras model
        """
        inputs = tf.keras.Input(shape=self.input_shape)

        # First convolutional block
        x = tf.keras.layers.Conv2D(32, (3, 3), activation='relu', padding='same')(inputs)
        x = tf.keras.layers.BatchNormalization()(x)
        x = tf.keras.layers.MaxPooling2D((2, 2))(x)
        x = tf.keras.layers.Dropout(0.25)(x)

        # Second convolutional block
        x = tf.keras.layers.Conv2D(64, (3, 3), activation='relu', padding='same')(x)
        x = tf.keras.layers.BatchNormalization()(x)
        x = tf.keras.layers.MaxPooling2D((2, 2))(x)
        x = tf.keras.layers.Dropout(0.25)(x)

        # Third convolutional block
        x = tf.keras.layers.Conv2D(128, (3, 3), activation='relu', padding='same')(x)
        x = tf.keras.layers.BatchNormalization()(x)
        x = tf.keras.layers.MaxPooling2D((2, 2))(x)
        x = tf.keras.layers.Dropout(0.25)(x)

        # Fourth convolutional block
        x = tf.keras.layers.Conv2D(256, (3, 3), activation='relu', padding='same')(x)
        x = tf.keras.layers.BatchNormalization()(x)
        x = tf.keras.layers.MaxPooling2D((2, 2))(x)
        x = tf.keras.layers.Dropout(0.25)(x)

        # Global average pooling
        x = tf.keras.layers.GlobalAveragePooling2D()(x)

        # Dense layers
        x = tf.keras.layers.Dense(512, activation='relu')(x)
        x = tf.keras.layers.BatchNormalization()(x)
        x = tf.keras.layers.Dropout(self.config.model.dropout_rate)(x)

        x = tf.keras.layers.Dense(256, activation='relu')(x)
        x = tf.keras.layers.BatchNormalization()(x)
        x = tf.keras.layers.Dropout(self.config.model.dropout_rate)(x)

        # Output layer
        outputs = tf.keras.layers.Dense(
            self.num_classes,
            activation='softmax',
            name='classification_output'
        )(x)

        # Create model
        self.model = tf.keras.Model(inputs=inputs, outputs=outputs, name='BasicCNN')

        # Compile model
        self.compile_model()

        logger.info("BasicCNN model built successfully")
        return self.model

    def get_model_info(self) -> dict:
        """
        Get detailed information about the model.

        Returns:
            Dictionary with model information
        """
        info = super().get_model_info()
        info.update({
            'architecture': 'Basic CNN',
            'num_conv_blocks': 4,
            'filters': [32, 64, 128, 256],
            'dense_units': [512, 256],
            'use_batch_norm': True,
            'use_dropout': True,
            'global_pooling': 'GlobalAveragePooling2D'
        })
        return info