import tensorflow as tf
from tensorflow.keras.models import Sequential
from tensorflow.keras.layers import Conv2D, MaxPooling2D, Flatten, Dense, Dropout
from tensorflow.keras.preprocessing.image import ImageDataGenerator
from sklearn.model_selection import train_test_split, KFold
from sklearn.metrics import classification_report, confusion_matrix
from sklearn.model_selection import GridSearchCV
from tensorflow.keras.wrappers.scikit_learn import KerasClassifier
import numpy as np
import os
import cv2
from pathlib import Path

class DrowningDetectionCNN:
    """
    CNN model for drowning detection in swimming scenarios.
    """

    def __init__(self, input_shape=(224, 224, 3), num_classes=2):
        self.input_shape = input_shape
        self.num_classes = num_classes
        self.model = None

    def build_model(self):
        """
        Build the CNN architecture.
        """
        self.model = Sequential([
            Conv2D(32, (3, 3), activation='relu', input_shape=self.input_shape),
            MaxPooling2D((2, 2)),
            Conv2D(64, (3, 3), activation='relu'),
            MaxPooling2D((2, 2)),
            Conv2D(128, (3, 3), activation='relu'),
            MaxPooling2D((2, 2)),
            Flatten(),
            Dense(128, activation='relu'),
            Dropout(0.5),
            Dense(self.num_classes, activation='softmax')
        ])

        self.model.compile(optimizer='adam',
                          loss='categorical_crossentropy',
                          metrics=['accuracy'])

        print("CNN model built successfully")
        return self.model

    def create_data_generators(self, data_dir, batch_size=32, validation_split=0.2):
        """
        Create data generators for training and validation with augmentation.
        """
        datagen = ImageDataGenerator(
            rescale=1./255,
            rotation_range=20,
            width_shift_range=0.2,
            height_shift_range=0.2,
            shear_range=0.2,
            zoom_range=0.2,
            horizontal_flip=True,
            fill_mode='nearest',
            validation_split=validation_split
        )

        train_generator = datagen.flow_from_directory(
            data_dir,
            target_size=self.input_shape[:2],
            batch_size=batch_size,
            class_mode='categorical',
            subset='training'
        )

        validation_generator = datagen.flow_from_directory(
            data_dir,
            target_size=self.input_shape[:2],
            batch_size=batch_size,
            class_mode='categorical',
            subset='validation'
        )

        return train_generator, validation_generator

    def train(self, train_generator, validation_generator, epochs=50):
        """
        Train the model with early stopping and model checkpointing.
        """
        callbacks = [
            tf.keras.callbacks.EarlyStopping(
                monitor='val_loss',
                patience=10,
                restore_best_weights=True
            ),
            tf.keras.callbacks.ModelCheckpoint(
                'models/drowning_cnn_best.h5',
                monitor='val_accuracy',
                save_best_only=True
            )
        ]

        history = self.model.fit(
            train_generator,
            epochs=epochs,
            validation_data=validation_generator,
            callbacks=callbacks
        )

        return history

    def evaluate(self, test_generator):
        """
        Evaluate the model on test data.
        """
        loss, accuracy = self.model.evaluate(test_generator)
        print(f"Test Loss: {loss:.4f}")
        print(f"Test Accuracy: {accuracy:.4f}")

        # Get predictions
        y_pred = self.model.predict(test_generator)
        y_pred_classes = np.argmax(y_pred, axis=1)
        y_true = test_generator.classes

        # Classification report
        class_names = list(test_generator.class_indices.keys())
        print("\nClassification Report:")
        print(classification_report(y_true, y_pred_classes, target_names=class_names))

        # Confusion matrix
        print("\nConfusion Matrix:")
        print(confusion_matrix(y_true, y_pred_classes))

        return accuracy

    def save_model(self, filepath):
        """
        Save the trained model.
        """
        self.model.save(filepath)
        print(f"Model saved to {filepath}")

    def load_model(self, filepath):
        """
        Load a trained model.
        """
        self.model = tf.keras.models.load_model(filepath)
        print(f"Model loaded from {filepath}")

    def predict_single_image(self, image_path):
        """
        Predict drowning risk for a single image.
        """
        img = cv2.imread(image_path)
        img = cv2.resize(img, self.input_shape[:2])
        img = img / 255.0
        img = np.expand_dims(img, axis=0)

        prediction = self.model.predict(img)
        class_idx = np.argmax(prediction)

        class_names = ['normal', 'drowning']
        return class_names[class_idx], prediction[0][class_idx]

    def cross_validate(self, data_dir, k=5, epochs=20):
        """
        Perform k-fold cross-validation.
        """
        datagen = ImageDataGenerator(rescale=1./255)

        # Get all data
        generator = datagen.flow_from_directory(
            data_dir,
            target_size=self.input_shape[:2],
            batch_size=32,
            class_mode='categorical',
            shuffle=False
        )

        # Get data as arrays
        x_data, y_data = [], []
        for i in range(len(generator)):
            x_batch, y_batch = generator[i]
            x_data.extend(x_batch)
            y_data.extend(y_batch)

        x_data = np.array(x_data)
        y_data = np.array(y_data)

        # K-fold cross-validation
        kf = KFold(n_splits=k, shuffle=True, random_state=42)
        fold_accuracies = []

        for fold, (train_idx, val_idx) in enumerate(kf.split(x_data)):
            print(f"Fold {fold + 1}/{k}")

            x_train, x_val = x_data[train_idx], x_data[val_idx]
            y_train, y_val = y_data[train_idx], y_data[val_idx]

            # Build and train model
            self.build_model()
            self.model.fit(x_train, y_train, epochs=epochs, verbose=0)

            # Evaluate
            loss, accuracy = self.model.evaluate(x_val, y_val, verbose=0)
            fold_accuracies.append(accuracy)
            print(f"Fold {fold + 1} accuracy: {accuracy:.4f}")

        mean_accuracy = np.mean(fold_accuracies)
        std_accuracy = np.std(fold_accuracies)

        print(f"\nCross-validation results:")
        print(f"Mean accuracy: {mean_accuracy:.4f} ± {std_accuracy:.4f}")

        return mean_accuracy, std_accuracy

    def hyperparameter_tuning(self, data_dir, param_grid):
        """
        Perform hyperparameter tuning using GridSearchCV.
        """
        def create_model(learning_rate=0.001, dropout_rate=0.5):
            model = Sequential([
                Conv2D(32, (3, 3), activation='relu', input_shape=self.input_shape),
                MaxPooling2D((2, 2)),
                Conv2D(64, (3, 3), activation='relu'),
                MaxPooling2D((2, 2)),
                Conv2D(128, (3, 3), activation='relu'),
                MaxPooling2D((2, 2)),
                Flatten(),
                Dense(128, activation='relu'),
                Dropout(dropout_rate),
                Dense(self.num_classes, activation='softmax')
            ])

            optimizer = tf.keras.optimizers.Adam(learning_rate=learning_rate)
            model.compile(optimizer=optimizer,
                         loss='categorical_crossentropy',
                         metrics=['accuracy'])
            return model

        # Wrap model for sklearn
        model = KerasClassifier(build_fn=create_model, epochs=10, batch_size=32, verbose=0)

        # Get sample data for tuning
        datagen = ImageDataGenerator(rescale=1./255, validation_split=0.2)
        train_generator = datagen.flow_from_directory(
            data_dir,
            target_size=self.input_shape[:2],
            batch_size=32,
            class_mode='categorical',
            subset='training'
        )

        # Grid search
        grid = GridSearchCV(estimator=model, param_grid=param_grid, cv=3, verbose=2)
        grid_result = grid.fit(train_generator)

        print(f"Best parameters: {grid_result.best_params_}")
        print(f"Best accuracy: {grid_result.best_score_:.4f}")

        return grid_result.best_params_, grid_result.best_score_

def main():
    # Initialize the CNN
    cnn = DrowningDetectionCNN()

    # Data directory
    data_dir = '../data/final'  # Use processed data

    if os.path.exists(data_dir):
        print("Performing cross-validation...")
        mean_acc, std_acc = cnn.cross_validate(data_dir, k=5)

        print("\nPerforming hyperparameter tuning...")
        param_grid = {
            'learning_rate': [0.001, 0.01],
            'dropout_rate': [0.3, 0.5],
            'batch_size': [16, 32]
        }
        best_params, best_score = cnn.hyperparameter_tuning(data_dir, param_grid)

        print("\nTraining final model with best parameters...")
        # Build model with best parameters
        cnn.build_model()

        # Create data generators
        train_gen, val_gen = cnn.create_data_generators(data_dir)

        # Train the model
        history = cnn.train(train_gen, val_gen)

        # Save the model
        os.makedirs('../models', exist_ok=True)
        cnn.save_model('../models/drowning_cnn.h5')

        print("Training complete!")
    else:
        print("Data directory not found. Please ensure data is available.")

if __name__ == "__main__":
    main()