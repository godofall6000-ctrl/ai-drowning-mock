import os
import numpy as np
from tensorflow.keras.preprocessing.image import ImageDataGenerator
from sklearn.metrics import classification_report, confusion_matrix, roc_curve, auc
import matplotlib.pyplot as plt
from drowning_cnn import DrowningDetectionCNN

def evaluate_on_test_set(model_path, test_data_dir):
    """
    Evaluate the trained model on the held-out test set.
    """
    # Load the trained model
    cnn = DrowningDetectionCNN()
    cnn.load_model(model_path)

    # Create test data generator
    test_datagen = ImageDataGenerator(rescale=1./255)

    test_generator = test_datagen.flow_from_directory(
        test_data_dir,
        target_size=cnn.input_shape[:2],
        batch_size=32,
        class_mode='categorical',
        shuffle=False
    )

    # Evaluate the model
    print("Evaluating model on test set...")
    accuracy = cnn.evaluate(test_generator)

    # Get predictions for ROC curve
    y_pred_prob = cnn.model.predict(test_generator)
    y_true = test_generator.classes
    y_pred = np.argmax(y_pred_prob, axis=1)

    # ROC curve for drowning class (assuming class 1 is drowning)
    fpr, tpr, thresholds = roc_curve(y_true, y_pred_prob[:, 1])
    roc_auc = auc(fpr, tpr)

    # Plot ROC curve
    plt.figure()
    plt.plot(fpr, tpr, color='darkorange', lw=2, label=f'ROC curve (area = {roc_auc:.2f})')
    plt.plot([0, 1], [0, 1], color='navy', lw=2, linestyle='--')
    plt.xlim([0.0, 1.0])
    plt.ylim([0.0, 1.05])
    plt.xlabel('False Positive Rate')
    plt.ylabel('True Positive Rate')
    plt.title('Receiver Operating Characteristic (ROC) Curve')
    plt.legend(loc="lower right")
    plt.savefig('../models/roc_curve.png')
    plt.show()

    print(f"ROC AUC: {roc_auc:.4f}")

    return accuracy, roc_auc

def main():
    model_path = '../models/drowning_cnn.h5'
    test_data_dir = '../data/final'  # Assuming test data is in final directory

    if os.path.exists(model_path) and os.path.exists(test_data_dir):
        accuracy, roc_auc = evaluate_on_test_set(model_path, test_data_dir)
        print("\nFinal Results:")
        print(f"Test Accuracy: {accuracy:.4f}")
        print(f"ROC AUC: {roc_auc:.4f}")
    else:
        print("Model or test data not found. Please train the model first.")

if __name__ == "__main__":
    main()