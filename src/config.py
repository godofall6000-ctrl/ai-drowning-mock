"""
Configuration management for AI Drowning Detection System.
Provides centralized configuration with environment-specific settings.
"""

import os
import json
from pathlib import Path
from typing import Dict, Any, Optional
from dataclasses import dataclass, asdict
from enum import Enum


class Environment(Enum):
    """Environment types."""
    DEVELOPMENT = "development"
    TESTING = "testing"
    PRODUCTION = "production"


class ModelArchitecture(Enum):
    """Available model architectures."""
    BASIC_CNN = "basic_cnn"
    RESNET50 = "resnet50"
    EFFICIENTNET_B0 = "efficientnet_b0"
    MOBILENET_V2 = "mobilenet_v2"


@dataclass
class DataConfig:
    """Data processing configuration."""
    input_shape: tuple = (224, 224, 3)
    batch_size: int = 32
    validation_split: float = 0.2
    test_split: float = 0.1
    augmentation: bool = True
    rotation_range: int = 20
    width_shift_range: float = 0.2
    height_shift_range: float = 0.2
    shear_range: float = 0.2
    zoom_range: float = 0.2
    horizontal_flip: bool = True
    fill_mode: str = "nearest"


@dataclass
class ModelConfig:
    """Model training configuration."""
    architecture: ModelArchitecture = ModelArchitecture.BASIC_CNN
    num_classes: int = 2
    learning_rate: float = 0.001
    dropout_rate: float = 0.5
    epochs: int = 50
    early_stopping_patience: int = 10
    reduce_lr_patience: int = 5
    reduce_lr_factor: float = 0.5
    min_lr: float = 1e-6


@dataclass
class TrainingConfig:
    """Training pipeline configuration."""
    cross_validation_folds: int = 5
    hyperparameter_tuning: bool = True
    save_best_only: bool = True
    save_weights_only: bool = False
    monitor_metric: str = "val_accuracy"
    log_dir: str = "logs"
    checkpoint_dir: str = "checkpoints"


@dataclass
class APIConfig:
    """API server configuration."""
    host: str = "0.0.0.0"
    port: int = 5000
    debug: bool = False
    max_content_length: int = 16 * 1024 * 1024  # 16MB
    upload_folder: str = "uploads"
    allowed_extensions: tuple = ("png", "jpg", "jpeg", "mp4", "avi", "mov")


@dataclass
class LoggingConfig:
    """Logging configuration."""
    level: str = "INFO"
    format: str = "%(asctime)s - %(name)s - %(levelname)s - %(message)s"
    file_path: str = "logs/drowning_detection.log"
    max_file_size: int = 10 * 1024 * 1024  # 10MB
    backup_count: int = 5


@dataclass
class MLflowConfig:
    """MLflow experiment tracking configuration."""
    experiment_name: str = "drowning_detection"
    tracking_uri: Optional[str] = None
    artifact_location: Optional[str] = None
    run_name_prefix: str = "training_run"


@dataclass
class Config:
    """Main configuration class."""
    environment: Environment = Environment.DEVELOPMENT
    data: DataConfig = DataConfig()
    model: ModelConfig = ModelConfig()
    training: TrainingConfig = TrainingConfig()
    api: APIConfig = APIConfig()
    logging: LoggingConfig = LoggingConfig()
    mlflow: MLflowConfig = MLflowConfig()

    def __post_init__(self):
        """Load environment-specific configuration."""
        self._load_from_env()
        self._load_from_file()

    def _load_from_env(self):
        """Load configuration from environment variables."""
        # Environment
        env = os.getenv("DROWNING_ENV", "development")
        self.environment = Environment(env.lower())

        # API settings
        self.api.host = os.getenv("API_HOST", self.api.host)
        self.api.port = int(os.getenv("API_PORT", self.api.port))
        self.api.debug = os.getenv("API_DEBUG", str(self.api.debug)).lower() == "true"

        # Model settings
        arch = os.getenv("MODEL_ARCHITECTURE", self.model.architecture.value)
        self.model.architecture = ModelArchitecture(arch.lower())

        # MLflow settings
        self.mlflow.tracking_uri = os.getenv("MLFLOW_TRACKING_URI")
        self.mlflow.experiment_name = os.getenv("MLFLOW_EXPERIMENT_NAME", self.mlflow.experiment_name)

    def _load_from_file(self):
        """Load configuration from JSON file."""
        config_file = Path("config.json")
        if config_file.exists():
            try:
                with open(config_file, "r") as f:
                    data = json.load(f)

                # Update nested configurations
                for key, value in data.items():
                    if hasattr(self, key):
                        attr = getattr(self, key)
                        if hasattr(attr, "__dict__"):
                            for sub_key, sub_value in value.items():
                                if hasattr(attr, sub_key):
                                    setattr(attr, sub_key, sub_value)
            except (json.JSONDecodeError, KeyError) as e:
                print(f"Warning: Could not load config file: {e}")

    def save_to_file(self, filepath: str = "config.json"):
        """Save current configuration to JSON file."""
        config_dict = asdict(self)

        # Convert enums to values
        config_dict["environment"] = self.environment.value
        config_dict["model"]["architecture"] = self.model.architecture.value

        with open(filepath, "w") as f:
            json.dump(config_dict, f, indent=2)

    @classmethod
    def from_dict(cls, config_dict: Dict[str, Any]) -> "Config":
        """Create Config instance from dictionary."""
        # Handle enum conversions
        if "environment" in config_dict:
            config_dict["environment"] = Environment(config_dict["environment"])
        if "model" in config_dict and "architecture" in config_dict["model"]:
            config_dict["model"]["architecture"] = ModelArchitecture(
                config_dict["model"]["architecture"]
            )

        # Create nested dataclasses
        data_config = DataConfig(**config_dict.get("data", {}))
        model_config = ModelConfig(**config_dict.get("model", {}))
        training_config = TrainingConfig(**config_dict.get("training", {}))
        api_config = APIConfig(**config_dict.get("api", {}))
        logging_config = LoggingConfig(**config_dict.get("logging", {}))
        mlflow_config = MLflowConfig(**config_dict.get("mlflow", {}))

        return cls(
            environment=config_dict.get("environment", Environment.DEVELOPMENT),
            data=data_config,
            model=model_config,
            training=training_config,
            api=api_config,
            logging=logging_config,
            mlflow=mlflow_config,
        )


# Global configuration instance
config = Config()


def get_config() -> Config:
    """Get the global configuration instance."""
    return config


def update_config(updates: Dict[str, Any]):
    """Update global configuration with new values."""
    global config
    config_dict = asdict(config)
    _deep_update(config_dict, updates)
    config = Config.from_dict(config_dict)


def _deep_update(base_dict: Dict[str, Any], updates: Dict[str, Any]):
    """Deep update dictionary."""
    for key, value in updates.items():
        if isinstance(value, dict) and key in base_dict and isinstance(base_dict[key], dict):
            _deep_update(base_dict[key], value)
        else:
            base_dict[key] = value


# Environment-specific configurations
def load_development_config() -> Config:
    """Load development configuration."""
    config = Config()
    config.api.debug = True
    config.logging.level = "DEBUG"
    return config


def load_production_config() -> Config:
    """Load production configuration."""
    config = Config()
    config.environment = Environment.PRODUCTION
    config.api.debug = False
    config.logging.level = "WARNING"
    config.training.epochs = 100
    return config


def load_testing_config() -> Config:
    """Load testing configuration."""
    config = Config()
    config.environment = Environment.TESTING
    config.api.debug = False
    config.training.epochs = 5  # Faster testing
    config.training.cross_validation_folds = 2
    return config


# Configuration factory
def create_config(env: str = None) -> Config:
    """Create configuration based on environment."""
    if env is None:
        env = os.getenv("DROWNING_ENV", "development")

    env = env.lower()
    if env == "production":
        return load_production_config()
    elif env == "testing":
        return load_testing_config()
    else:
        return load_development_config()