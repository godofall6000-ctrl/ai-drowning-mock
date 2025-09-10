import requests
import zipfile
import os
from pathlib import Path

def download_dataset(url, save_path):
    """
    Download a dataset from a URL and extract it.
    """
    print(f"Downloading dataset from {url}...")

    response = requests.get(url, stream=True)
    response.raise_for_status()

    with open(save_path, 'wb') as f:
        for chunk in response.iter_content(chunk_size=8192):
            f.write(chunk)

    print(f"Downloaded to {save_path}")

    # If it's a zip file, extract it
    if save_path.endswith('.zip'):
        print("Extracting zip file...")
        with zipfile.ZipFile(save_path, 'r') as zip_ref:
            zip_ref.extractall(os.path.dirname(save_path))
        os.remove(save_path)  # Remove zip after extraction
        print("Extraction complete")

def main():
    # Create data directory if not exists
    data_dir = Path('../data')
    data_dir.mkdir(exist_ok=True)

    # Example datasets - replace with actual URLs
    datasets = [
        {
            'url': 'https://example.com/drowning_dataset.zip',  # Replace with real URL
            'filename': 'drowning_dataset.zip'
        },
        # Add more datasets as needed
    ]

    for dataset in datasets:
        save_path = data_dir / dataset['filename']
        try:
            download_dataset(dataset['url'], str(save_path))
        except Exception as e:
            print(f"Failed to download {dataset['url']}: {e}")

if __name__ == "__main__":
    main()