#!/usr/bin/env python3
"""
Simple training script for AI Drowning Detection System.
Generates synthetic data and trains all models.
"""

import os
import sys
from pathlib import Path

# Add src to path
sys.path.insert(0, str(Path(__file__).parent / "src"))

from src.train_models import ModelTrainer
from src.config import get_config
from src.utils.logging_utils import setup_logging

def main():
    """Main training function."""
    print("🚀 AI Drowning Detection System - Training")
    print("=" * 50)

    # Setup logging
    setup_logging()

    # Get configuration
    config = get_config()
    config.model.epochs = 5  # Reduce epochs for demo

    print(f"Configuration loaded:")
    print(f"  - Input shape: {config.data.input_shape}")
    print(f"  - Batch size: {config.data.batch_size}")
    print(f"  - Epochs: {config.model.epochs}")
    print()

    # Create trainer
    trainer = ModelTrainer(config)

    # Generate synthetic data
    print("📊 Generating synthetic training data...")
    data_dir = trainer.generate_synthetic_data(num_samples=500)  # Smaller dataset for demo
    print(f"✅ Synthetic data generated at: {data_dir}")
    print()

    # Train all models
    print("🤖 Training AI models...")
    print("This may take several minutes depending on your hardware...")
    print()

    results = trainer.train_all_models(data_dir)

    # Print results
    trainer.print_summary(results)

    # Save results
    trainer.save_results(results, "training_results.json")

    print()
    print("🎉 Training completed!")
    print("📁 Models saved in 'models/' directory")
    print("📊 Results saved to 'training_results.json'")
    print()
    print("🚀 To start the web interface:")
    print("   python -m src.api.app")
    print()
    print("🌐 Open http://localhost:5000 in your browser")

if __name__ == "__main__":
    main()