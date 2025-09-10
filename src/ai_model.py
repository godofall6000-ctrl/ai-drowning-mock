import cv2
import numpy as np
from yolo_detector import get_detector

# Try to use YOLO detector, fallback to basic detection
try:
    yolo_detector = get_detector()
    USE_YOLO = yolo_detector.net is not None
except:
    USE_YOLO = False

# Fallback: Load TensorFlow if YOLO not available
if not USE_YOLO:
    try:
        from tensorflow.keras.applications import MobileNetV2
        from tensorflow.keras.preprocessing import image
        from tensorflow.keras.applications.mobilenet_v2 import preprocess_input, decode_predictions
        model = MobileNetV2(weights='imagenet')
        print("Using TensorFlow MobileNetV2 for detection")
    except ImportError:
        print("Warning: Neither YOLO nor TensorFlow available. Using basic detection only.")
        USE_TF = False
    else:
        USE_TF = True
else:
    print("Using YOLO for advanced detection")
    USE_TF = False

def detect_humans_in_frame(frame):
    """
    Advanced human detection using YOLO or fallback methods.
    Returns number of humans detected and their bounding boxes.
    """
    try:
        if USE_YOLO:
            # Use YOLO for precise detection
            detections = yolo_detector.detect_humans(frame)
            return len(detections), detections

        elif USE_TF:
            # Fallback to TensorFlow classification
            img = cv2.resize(frame, (224, 224))
            img_array = image.img_to_array(img)
            img_array = np.expand_dims(img_array, axis=0)
            img_array = preprocess_input(img_array)

            predictions = model.predict(img_array, verbose=0)
            decoded = decode_predictions(predictions, top=5)[0]

            human_detected = any('person' in pred[1].lower() for pred in decoded)
            return 1 if human_detected else 0, []

        else:
            # Basic fallback - assume human if motion detected elsewhere
            return 0, []

    except Exception as e:
        print(f"Error in AI detection: {e}")
        return 0, []

def analyze_drowning_risk(human_present, motion_detected, time_without_motion):
    """
    Analyze drowning risk based on human presence and motion.
    This is a basic heuristic for demonstration.
    """
    if not human_present:
        return "No human detected"

    if motion_detected:
        return "Active - Normal swimming"

    if time_without_motion > 10:  # seconds
        return "ALERT: Potential drowning - No motion detected"

    return "Monitoring - Minimal motion"

# Example usage
if __name__ == "__main__":
    # Test with a sample image (you would integrate this with video frames)
    test_frame = cv2.imread('../data/sample_image.jpg')  # Replace with actual path
    if test_frame is not None:
        human = detect_human_in_frame(test_frame)
        print(f"Human detected: {human}")
    else:
        print("No test image found")