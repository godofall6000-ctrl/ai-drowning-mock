import cv2
import numpy as np
import time
import sys
from ai_model import detect_humans_in_frame, analyze_drowning_risk
from database import log_alert

# Sound alert (Windows only)
try:
    import winsound
    SOUND_AVAILABLE = True
except ImportError:
    SOUND_AVAILABLE = False

def detect_motion(frame1, frame2):
    """
    Detect motion between two frames using frame differencing.
    Returns motion status and thresholded image.
    """
    # Convert frames to grayscale
    gray1 = cv2.cvtColor(frame1, cv2.COLOR_BGR2GRAY)
    gray2 = cv2.cvtColor(frame2, cv2.COLOR_BGR2GRAY)

    # Compute absolute difference
    diff = cv2.absdiff(gray1, gray2)

    # Apply threshold to get binary image
    thresh = cv2.threshold(diff, 25, 255, cv2.THRESH_BINARY)[1]

    # Dilate to fill holes
    thresh = cv2.dilate(thresh, None, iterations=2)

    # Find contours of moving objects
    contours, _ = cv2.findContours(thresh.copy(), cv2.RETR_EXTERNAL, cv2.CHAIN_APPROX_SIMPLE)

    # Determine if motion is detected
    motion_detected = len(contours) > 0

    return motion_detected, thresh

def main():
    """
    Main function to run the drowning detection system.
    Uses webcam or video file for input.
    """
    # Open video capture (0 for webcam, or path to video file)
    if len(sys.argv) > 1:
        video_path = sys.argv[1]
        cap = cv2.VideoCapture(video_path)
        print(f"Using video file: {video_path}")
    else:
        cap = cv2.VideoCapture(0)
        print("Using webcam")

    # Read first two frames
    ret, frame1 = cap.read()
    ret, frame2 = cap.read()

    if not ret:
        print("Error: Cannot read video feed")
        return

    print("AI Drowning Detection System Started")
    print("Press 'q' to quit")

    # Initialize tracking variables
    last_motion_time = time.time()
    frame_count = 0
    human_present = False

    while cap.isOpened():
        frame_count += 1

        # Detect motion
        motion, thresh = detect_motion(frame1, frame2)

        # Update motion time
        if motion:
            last_motion_time = time.time()

        # AI human detection every 30 frames (~1 second at 30fps)
        if frame_count % 30 == 0:
            human_count, detections = detect_humans_in_frame(frame2)
            human_present = human_count > 0

        # Calculate time without motion
        time_without_motion = time.time() - last_motion_time

        # Analyze drowning risk
        risk_status = analyze_drowning_risk(human_present, motion, time_without_motion)

        print(f"Status: {risk_status}")

        # Log alerts to database
        if "ALERT" in risk_status:
            log_alert("DROWNING_ALERT", f"Status: {risk_status}, Time without motion: {time_without_motion:.1f}s")
            # Sound alert
            if SOUND_AVAILABLE:
                try:
                    winsound.Beep(2000, 500)
                except:
                    pass

        # Display the thresholded image
        cv2.imshow('Motion Detection', thresh)

        # Update frames
        frame1 = frame2
        ret, frame2 = cap.read()

        if not ret:
            break

        # Exit on 'q' key press
        if cv2.waitKey(1) & 0xFF == ord('q'):
            break

    # Release resources
    cap.release()
    cv2.destroyAllWindows()
    print("System stopped")

if __name__ == "__main__":
    main()