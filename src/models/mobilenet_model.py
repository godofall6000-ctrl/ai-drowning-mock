"""
MobileNet architecture for drowning detection.
Implements MobileNetV2 with depthwise separable convolutions for efficiency.
"""

import tensorflow as tf
from typing import Optional

from .base_model import BaseModel
from ..config import Config, get_config
from ..utils.logging_utils import get_logger

logger = get_logger(__name__)


class MobileNetModel(BaseModel):
    """
    MobileNetV2-based model for drowning detection.
    Optimized for mobile and edge devices with efficient architecture.
    """

    def __init__(
        self,
        config: Optional[Config] = None,
        alpha: float = 1.0,
        include_top: bool = True,
        weights: str = 'imagenet',
        freeze_base: bool = True
    ):
        """
        Initialize the MobileNet model.

        Args:
            config: Configuration object. If None, uses global config.
            alpha: Width multiplier for MobileNet (controls model size).
            include_top: Whether to include the classification head.
            weights: Pre-trained weights to use ('imagenet' or None).
            freeze_base: Whether to freeze the base model layers.
        """
        self.alpha = alpha
        self.include_top = include_top
        self.weights = weights
        self.freeze_base = freeze_base
        super().__init__(config)
        logger.info(f"MobileNetV2 (alpha={alpha}) model initialized")

    def build_model(self) -> tf.keras.Model:
        """
        Build the MobileNetV2 architecture with custom classification head.

        Returns:
            Compiled Keras model
        """
        # Load MobileNetV2 base model
        base_model = tf.keras.applications.MobileNetV2(
            include_top=False,
            weights=self.weights,
            input_shape=self.input_shape,
            alpha=self.alpha,
            pooling='avg'
        )

        # Freeze base model layers if specified
        if self.freeze_base:
            for layer in base_model.layers:
                layer.trainable = False
            logger.info("Base MobileNetV2 layers frozen")
        else:
            logger.info("Base MobileNetV2 layers trainable")

        inputs = tf.keras.Input(shape=self.input_shape)

        # Pass through base model
        x = base_model(inputs, training=not self.freeze_base)

        # Custom classification head optimized for mobile
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
        self.model = tf.keras.Model(inputs=inputs, outputs=outputs, name='MobileNetV2')

        # Compile model
        self.compile_model()

        logger.info("MobileNetV2 model built successfully")
        return self.model

    def get_model_info(self) -> dict:
        """
        Get detailed information about the model.

        Returns:
            Dictionary with model information
        """
        info = super().get_model_info()
        info.update({
            'architecture': 'MobileNetV2',
            'base_model': 'MobileNetV2',
            'alpha': self.alpha,
            'weights': self.weights,
            'freeze_base': self.freeze_base,
            'include_top': self.include_top,
            'dense_units': [512, 256],
            'use_batch_norm': True,
            'use_dropout': True,
            'depthwise_separable': True,
            'transfer_learning': True,
            'mobile_optimized': True
        })
        return info

    def quantize_model(self, quantization_type: str = 'dynamic'):
        """
        Apply quantization to the model for deployment optimization.

        Args:
            quantization_type: Type of quantization ('dynamic', 'float16', 'int8')
        """
        if self.model is None:
            logger.warning("Model not built yet. Call build_model() first.")
            return

        logger.info(f"Applying {quantization_type} quantization")

        if quantization_type == 'dynamic':
            # Dynamic range quantization
            converter = tf.lite.TFLiteConverter.from_keras_model(self.model)
            converter.optimizations = [tf.lite.Optimize.DEFAULT]
            quantized_model = converter.convert()

        elif quantization_type == 'float16':
            # Float16 quantization
            converter = tf.lite.TFLiteConverter.from_keras_model(self.model)
            converter.optimizations = [tf.lite.Optimize.DEFAULT]
            converter.target_spec.supported_types = [tf.float16]
            quantized_model = converter.convert()

        elif quantization_type == 'int8':
            # Full integer quantization (requires representative dataset)
            def representative_dataset():
                # This should use a representative sample of your data
                for _ in range(100):
                    data = tf.random.normal((1,) + self.input_shape)
                    yield [data]

            converter = tf.lite.TFLiteConverter.from_keras_model(self.model)
            converter.optimizations = [tf.lite.Optimize.DEFAULT]
            converter.representative_dataset = representative_dataset
            converter.target_spec.supported_ops = [tf.lite.OpsSet.TFLITE_BUILTINS_INT8]
            converter.inference_input_type = tf.int8
            converter.inference_output_type = tf.int8
            quantized_model = converter.convert()

        else:
            raise ValueError(f"Unsupported quantization type: {quantization_type}")

        # Save quantized model
        quantized_path = f"models/{self.model_name}_{quantization_type}_quantized.tflite"
        with open(quantized_path, 'wb') as f:
            f.write(quantized_model)

        logger.info(f"Quantized model saved to {quantized_path}")

        # Calculate model size reduction
        original_size = self.model.count_params()
        # Note: TFLite model size would need separate calculation

        return quantized_path

    def benchmark_inference(self, num_runs: int = 100) -> dict:
        """
        Benchmark inference performance.

        Args:
            num_runs: Number of inference runs for benchmarking

        Returns:
            Dictionary with performance metrics
        """
        if self.model is None:
            logger.warning("Model not built yet. Call build_model() first.")
            return {}

        import time

        # Create dummy input
        dummy_input = tf.random.normal((1,) + self.input_shape)

        # Warm up
        _ = self.model.predict(dummy_input, verbose=0)

        # Benchmark
        start_time = time.time()
        for _ in range(num_runs):
            _ = self.model.predict(dummy_input, verbose=0)
        end_time = time.time()

        avg_inference_time = (end_time - start_time) / num_runs
        fps = 1.0 / avg_inference_time

        metrics = {
            'avg_inference_time_ms': avg_inference_time * 1000,
            'fps': fps,
            'model_size_mb': self.model.count_params() * 4 / (1024 * 1024),  # Rough estimate
        }

        logger.info(f"Benchmark results: {metrics}")
        return metrics

    def optimize_for_mobile(self):
        """
        Apply mobile-specific optimizations to the model.
        """
        if self.model is None:
            logger.warning("Model not built yet. Call build_model() first.")
            return

        logger.info("Applying mobile optimizations")

        # Convert to TFLite with optimizations
        converter = tf.lite.TFLiteConverter.from_keras_model(self.model)
        converter.optimizations = [
            tf.lite.Optimize.DEFAULT,
            tf.lite.Optimize.EXPERIMENTAL_SPARSITY
        ]

        # Enable GPU delegation if available
        converter.target_spec.supported_ops = [
            tf.lite.OpsSet.TFLITE_BUILTINS,
            tf.lite.OpsSet.SELECT_TF_OPS  # Enable TensorFlow ops
        ]

        tflite_model = converter.convert()

        # Save optimized model
        optimized_path = f"models/{self.model_name}_mobile_optimized.tflite"
        with open(optimized_path, 'wb') as f:
            f.write(tflite_model)

        logger.info(f"Mobile-optimized model saved to {optimized_path}")
        return optimized_path