# AI Drowning Detection System - Technical Documentation

## System Architecture

### Overview
The AI Drowning Detection System is a computer vision-based application that analyzes video feeds from cameras positioned around water bodies to detect potential drowning incidents. The system combines traditional computer vision techniques with modern AI models to provide real-time monitoring and alerting.

### Core Components

#### 1. Motion Detection Module (`src/detection.py`)
- **Purpose**: Detects movement in video streams using frame differencing
- **Technology**: OpenCV computer vision library
- **Algorithm**:
  1. Convert consecutive frames to grayscale
  2. Compute absolute difference between frames
  3. Apply Gaussian blur to reduce noise
  4. Threshold the difference image
  5. Dilate to fill holes and connect components
  6. Find contours of moving objects
  7. Determine motion based on contour count and area

#### 2. AI Analysis Module (`src/ai_model.py`)
- **Purpose**: Intelligent analysis of video frames for human presence
- **Technology**: TensorFlow/Keras with pre-trained MobileNetV2
- **Features**:
  - Human detection using deep learning
  - Periodic analysis to balance performance and accuracy
  - Risk assessment based on motion and human presence

#### 3. Database Module (`src/database.py`)
- **Purpose**: Persistent logging of system alerts and events
- **Technology**: SQLite database (built-in Python)
- **Features**:
  - Timestamped alert logging
  - Query functionality for recent alerts
  - Lightweight and portable

#### 4. Testing Module (`src/test_system.py`)
- **Purpose**: Validate system components and functionality
- **Features**:
  - Unit tests for motion detection
  - AI model validation
  - Synthetic test data generation

#### 5. Recording Module (`src/record_video.py`)
- **Purpose**: Capture video from webcam for testing and data collection
- **Features**:
  - Real-time video recording
  - Configurable output format
  - Simple user interface

## Algorithm Details

### Motion Detection Algorithm
```python
def detect_motion(frame1, frame2):
    gray1 = cv2.cvtColor(frame1, cv2.COLOR_BGR2GRAY)
    gray2 = cv2.cvtColor(frame2, cv2.COLOR_BGR2GRAY)
    diff = cv2.absdiff(gray1, gray2)
    thresh = cv2.threshold(diff, 25, 255, cv2.THRESH_BINARY)[1]
    thresh = cv2.dilate(thresh, None, iterations=2)
    contours, _ = cv2.findContours(thresh.copy(), cv2.RETR_EXTERNAL, cv2.CHAIN_APPROX_SIMPLE)
    motion_detected = len(contours) > 0
    return motion_detected, thresh
```

### AI Human Detection
```python
def detect_human_in_frame(frame):
    img = cv2.resize(frame, (224, 224))
    img_array = image.img_to_array(img)
    img_array = np.expand_dims(img_array, axis=0)
    img_array = preprocess_input(img_array)
    predictions = model.predict(img_array)
    decoded = decode_predictions(predictions, top=5)[0]
    human_detected = any('person' in pred[1].lower() for pred in decoded)
    return human_detected
```

### Drowning Risk Assessment Logic
- **No human detected**: System reports no activity
- **Motion detected**: Indicates active swimming or movement
- **No motion for >10 seconds**: Triggers drowning alert
- **AI confirmation**: Periodic human presence verification

## Data Flow Diagram

```
Video Input → Frame Capture → Motion Detection → AI Analysis → Risk Assessment → Alert Logging
     ↑              ↓              ↓              ↓              ↓              ↓
  Webcam/Video   Frame Buffer   Motion Status   Human Present   Risk Status   Database
```

## Performance Characteristics

- **Frame Rate**: Real-time processing (30 FPS target)
- **AI Inference**: Every 30 frames (~1 second intervals)
- **CPU Usage**: Moderate (depends on hardware)
- **Memory Usage**: ~200-500MB with TensorFlow model loaded
- **Storage**: Minimal (SQLite database, video files optional)

## System Requirements

### Software Requirements
- Python 3.8 or higher
- OpenCV 4.x
- TensorFlow 2.x with Keras
- NumPy
- SQLite3 (built-in with Python)

### Hardware Requirements
- Webcam or video capture device
- Minimum 4GB RAM
- Multi-core CPU recommended
- GPU optional (for faster AI inference)

## Installation and Setup

1. Install Python dependencies:
   ```bash
   pip install opencv-python tensorflow numpy
   ```

2. Run the system:
   ```bash
   python src/detection.py
   ```

3. For testing:
   ```bash
   python src/test_system.py
   ```

## Configuration

The system can be configured by modifying parameters in the source code:

- Motion detection threshold: `thresh_value = 25`
- Alert timeout: `time_without_motion > 10`
- AI analysis frequency: `frame_count % 30 == 0`

## Limitations and Known Issues

1. **False Positives**: Motion detection may trigger on water ripples, reflections
2. **Lighting Sensitivity**: Performance varies with lighting conditions
3. **AI Model Limitations**: Pre-trained model not specifically trained for drowning detection
4. **Single Camera**: Currently supports only one video stream
5. **No Real-time Alerts**: Console output only (can be extended)

## Future Enhancements

- **Custom ML Model**: Train drowning-specific classifier
- **Pose Estimation**: Use MediaPipe for detailed body position analysis
- **Multi-camera Support**: Handle multiple video feeds simultaneously
- **Alert System**: Email/SMS notifications, siren activation
- **Cloud Integration**: Remote monitoring and storage
- **Mobile App**: Companion app for alerts and configuration
- **Advanced Analytics**: Historical trend analysis and reporting

## Testing and Validation

### Unit Tests
- Motion detection accuracy
- AI model prediction reliability
- Database operations

### Integration Tests
- End-to-end video processing
- Alert generation and logging
- Performance under load

### Validation Methods
- Synthetic test videos
- Real-world pool/lake footage
- Comparison with manual observation

## Ethical Considerations

- **Privacy**: Ensure compliance with data protection regulations
- **Safety**: Never use for actual emergency response without professional validation
- **Testing**: Use controlled environments for system testing
- **Transparency**: Clearly communicate system limitations

## Maintenance and Support

- **Logging**: All alerts and system events are logged to SQLite database
- **Monitoring**: Console output for real-time status
- **Updates**: Modular design allows for easy component updates
- **Backup**: Database files should be regularly backed up

## Conclusion

This AI Drowning Detection System provides a solid foundation for drowning prevention technology. While the current implementation uses basic algorithms, it demonstrates the potential of combining computer vision and AI for life-saving applications. The modular architecture allows for easy extension and improvement as more advanced techniques become available.