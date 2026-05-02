"""
Brilliantly Bussy ImagGoo - Main Application Entry Point
NSFW Uncensored Generative AI Platform
"""
import os
import sys
from pathlib import Path

# Add project root to path
project_root = Path(__file__).parent
sys.path.insert(0, str(project_root))

from fastapi import FastAPI
from fastapi.middleware.cors import CORSMiddleware
from fastapi.staticfiles import StaticFiles
import uvicorn
import yaml
from loguru import logger

# Load configuration
def load_config():
    config_path = project_root / "config" / "settings.yaml"
    with open(config_path, 'r') as f:
        return yaml.safe_load(f)

config = load_config()

# Initialize FastAPI app
app = FastAPI(
    title=config['app']['name'],
    version=config['app']['version'],
    description=config['app']['description']
)

# CORS middleware
app.add_middleware(
    CORSMiddleware,
    allow_origins=config['server']['cors_origins'],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

# Mount static files
output_dir = project_root / "output"
if output_dir.exists():
    app.mount("/output", StaticFiles(directory=str(output_dir)), name="output")

# Import and include routers
from src.api import image_router, video_router, text_router, body_router, income_router

app.include_router(image_router.router, prefix="/api/v1/image", tags=["Image Generation"])
app.include_router(video_router.router, prefix="/api/v1/video", tags=["Video Generation"])
app.include_router(text_router.router, prefix="/api/v1/text", tags=["Text/Novel Generation"])
app.include_router(body_router.router, prefix="/api/v1/body", tags=["Body Mapping"])
app.include_router(income_router.router, prefix="/api/v1/income", tags=["Income Automation"])

@app.get("/")
async def root():
    return {
        "name": config['app']['name'],
        "version": config['app']['version'],
        "description": config['app']['description'],
        "endpoints": {
            "docs": "/docs",
            "image_generation": "/api/v1/image",
            "video_generation": "/api/v1/video",
            "text_generation": "/api/v1/text",
            "body_mapping": "/api/v1/body",
            "income_automation": "/api/v1/income"
        }
    }

@app.get("/health")
async def health_check():
    return {"status": "healthy"}

if __name__ == "__main__":
    logger.info(f"Starting {config['app']['name']} v{config['app']['version']}")
    uvicorn.run(
        "main:app",
        host=config['server']['host'],
        port=config['server']['port'],
        workers=config['server']['workers'],
        reload=True
    )
