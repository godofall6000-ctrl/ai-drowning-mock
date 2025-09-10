"""
Base model class for drowning detection CNN architectures.
Provides common functionality and interface for all models.
"""

import os
import numpy as np
import tensorflow as tf
from abc import ABC, abstractmethod
from typing import Tuple, Optional, Dict, Any, List
from pathlib import Path

from ..config import Config, get_config
from ..utils.logging_utils import get_logger

logger = get_logger(__name__)


class BaseModel(ABC):
    """
    Abstract base class for CNN models.
    Defines the interface that all model architectures must implement.
    """

    def __init__(self, config: Optional[Config] = None):
        """
        Initialize the model.

        Args:
            config: Configuration object. If None, uses global config.
        """
        self.config = config or get_config()
        self.model: Optional[tf.keras.Model] = None
        self.history: Optional[tf.keras.callbacks.History] = None
        self.input_shape = self.config.data.input_shape
        self.num_classes = self.config.model.num_classes

        # Build the model architecture
        self.build_model()

    @abstractmethod
    def build_model(self) -> tf.keras.Model:
        """
        Build the model architecture.
        Must be implemented by subclasses.

        Returns:
            Compiled Keras model
        """
        pass

    def compile_model(self, learning_rate: Optional[float] = None) -> None:
        """
        Compile the model with appropriate optimizer and loss function.

        Args:
            learning_rate: Learning rate for optimizer. If None, uses config value.
        """
        if learning_rate is None:
            learning_rate = self.config.model.learning_rate

        optimizer = tf.keras.optimizers.Adam(learning_rate=learning_rate)

        self.model.compile(
            optimizer=optimizer,
            loss='categorical_crossentropy',
            metrics=['accuracy', tf.keras.metrics.AUC(name='auc')]
        )

        logger.info(f"Model compiled with learning rate: {learning_rate}")

    def get_callbacks(self) -> List[tf.keras.callbacks.Callback]:
        """
        Get training callbacks.

        Returns:
            List of Keras callbacks
        """
        callbacks = []

        # Early stopping
        early_stopping = tf.keras.callbacks.EarlyStopping(
            monitor=self.config.training.monitor_metric,
            patience=self.config.model.early_stopping_patience,
            restore_best_weights=True,
            verbose=1
        )
        callbacks.append(early_stopping)

        # Model checkpoint
        checkpoint_path = Path(self.config.training.checkpoint_dir) / f"{self.__class__.__name__}_best.h5"
        checkpoint_path.parent.mkdir(parents=True, exist_ok=True)

        model_checkpoint = tf.keras.callbacks.ModelCheckpoint(
            filepath=str(checkpoint_path),
            monitor=self.config.training.monitor_metric,
            save_best_only=self.config.training.save_best_only,
            save_weights_only=self.config.training.save_weights_only,
            verbose=1
        )
        callbacks.append(model_checkpoint)

        # Reduce learning rate on plateau
        reduce_lr = tf.keras.callbacks.ReduceLROnPlateau(
            monitor=self.config.training.monitor_metric,
            factor=self.config.model.reduce_lr_factor,
            patience=self.config.model.reduce_lr_patience,
            min_lr=self.config.model.min_lr,
            verbose=1
        )
        callbacks.append(reduce_lr)

        # TensorBoard logging
        tensorboard = tf.keras.callbacks.TensorBoard(
            log_dir=self.config.training.log_dir,
            histogram_freq=1,
            write_graph=True,
            write_images=True,
            update_freq='epoch'
        )
        callbacks.append(tensorboard)

        return callbacks

    def train(
        self,
        train_generator: tf.keras.utils.Sequence,
        validation_generator: tf.keras.utils.Sequence,
        epochs: Optional[int] = None,
        callbacks: Optional[List[tf.keras.callbacks.Callback]] = None
    ) -> tf.keras.callbacks.History:
        """
        Train the model.

        Args:
            train_generator: Training data generator
            validation_generator: Validation data generator
            epochs: Number of epochs. If None, uses config value.
            callbacks: Additional callbacks. If None, uses default callbacks.

        Returns:
            Training history
        """
        if epochs is None:
            epochs = self.config.model.epochs

        if callbacks is None:
            callbacks = self.get_callbacks()

        logger.info(f"Starting training for {epochs} epochs")

        self.history = self.model.fit(
            train_generator,
            epochs=epochs,
            validation_data=validation_generator,
            callbacks=callbacks,
            verbose=1
        )

        logger.info("Training completed")
        return self.history

    def evaluate(
        self,
        test_generator: tf.keras.utils.Sequence
    ) -> Dict[str, float]:
        """
        Evaluate the model on test data.

        Args:
            test_generator: Test data generator

        Returns:
            Dictionary with evaluation metrics
        """
        logger.info("Evaluating model on test data")

        results = self.model.evaluate(test_generator, verbose=1)

        metrics = {}
        metric_names = ['loss', 'accuracy', 'auc']

        for name, value in zip(metric_names, results):
            metrics[name] = float(value)

        logger.info(f"Test results: {metrics}")
        return metrics

    def predict(
        self,
        data: np.ndarray,
        batch_size: Optional[int] = None
    ) -> np.ndarray:
        """
        Make predictions on input data.

        Args:
            data: Input data array
            batch_size: Batch size for prediction. If None, uses config value.

        Returns:
            Prediction probabilities
        """
        if batch_size is None:
            batch_size = self.config.data.batch_size

        return self.model.predict(data, batch_size=batch_size, verbose=0)

    def predict_classes(
        self,
        data: np.ndarray,
        batch_size: Optional[int] = None
    ) -> np.ndarray:
        """
        Predict class labels.

        Args:
            data: Input data array
            batch_size: Batch size for prediction. If None, uses config value.

        Returns:
            Predicted class indices
        """
        predictions = self.predict(data, batch_size)
        return np.argmax(predictions, axis=1)

    def save_model(self, filepath: str) -> None:
        """
        Save the model to disk.

        Args:
            filepath: Path to save the model
        """
        filepath = Path(filepath)
        filepath.parent.mkdir(parents=True, exist_ok=True)

        self.model.save(filepath)
        logger.info(f"Model saved to {filepath}")

    def load_model(self, filepath: str) -> None:
        """
        Load a model from disk.

        Args:
            filepath: Path to the saved model
        """
        self.model = tf.keras.models.load_model(filepath)
        logger.info(f"Model loaded from {filepath}")

    def get_model_summary(self) -> str:
        """
        Get model architecture summary.

        Returns:
            String representation of model summary
        """
        if self.model is None:
            return "Model not built yet"

        # Capture model summary
        from io import StringIO
        summary_io = StringIO()
        self.model.summary(print_fn=lambda x: summary_io.write(x + '\n'))
        return summary_io.getvalue()

    def get_flops(self) -> Optional[float]:
        """
        Calculate FLOPs for the model.

        Returns:
            Number of FLOPs or None if calculation fails
        """
        try:
            # This is an approximation - for exact calculation,
            # you might need additional tools like tf.profiler
            total_flops = 0

            for layer in self.model.layers:
                if hasattr(layer, 'get_flops'):
                    try:
                        flops = layer.get_flops()
                        total_flops += flops
                    except:
                        pass

            return total_flops if total_flops > 0 else None
        except Exception as e:
            logger.warning(f"Could not calculate FLOPs: {e}")
            return None

    def get_model_size(self) -> Dict[str, int]:
        """
        Get model size information.

        Returns:
            Dictionary with model size metrics
        """
        if self.model is None:
            return {}

        # Count parameters
        total_params = self.model.count_params()
        trainable_params = sum([
            tf.keras.backend.count_params(w) for w in self.model.trainable_weights
        ])
        non_trainable_params = sum([
            tf.keras.backend.count_params(w) for w in self.model.non_trainable_weights
        ])

        return {
            'total_parameters': total_params,
            'trainable_parameters': trainable_params,
            'non_trainable_parameters': non_trainable_params
        }

    @property
    def model_name(self) -> str:
        """Get the model name."""
        return self.__class__.__name__

    def __str__(self) -> str:
        """String representation of the model."""
        return f"{self.model_name}(input_shape={self.input_shape}, num_classes={self.num_classes})"

    def __repr__(self) -> str:
        """Detailed string representation."""
        return (f"{self.__class__.__name__}("
                f"input_shape={self.input_shape}, "
                f"num_classes={self.num_classes}, "
                f"built={self.model is not None})")