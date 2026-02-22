#!/usr/bin/env python3
"""
Minimal FastSD CPU/GPU API Server
CPU mode  : OpenVINO via optimum-intel (rupeshs/sd-turbo-openvino)
CUDA mode : diffusers AutoPipeline with torch.float16 (stabilityai/sd-turbo)

Usage:
  python src/api_server.py --port 8888              # auto-detect
  python src/api_server.py --port 8888 --device cpu
  python src/api_server.py --port 8888 --device cuda
"""

import base64
import io
import os
import sys
import time
from pathlib import Path
from argparse import ArgumentParser
from typing import Optional

import torch
import uvicorn
from fastapi import FastAPI, HTTPException
from fastapi.middleware.cors import CORSMiddleware
from pydantic import BaseModel, Field

# ---------------------------------------------------------------------------
# Device selection — resolved once at startup via --device arg
# ---------------------------------------------------------------------------
DEVICE: str = "cpu"  # overwritten in main() before any pipeline is loaded

DEFAULT_MODEL_CPU  = "rupeshs/sd-turbo-openvino"
DEFAULT_MODEL_CUDA = "stabilityai/sd-turbo"
DEFAULT_MODEL      = DEFAULT_MODEL_CPU  # updated in main()

app = FastAPI(
    title="FastSD CPU API",
    description="Minimal API for fast image generation on CPU using OpenVINO",
    version="1.0.0",
    docs_url="/api/docs",
    redoc_url="/api/redoc",
    openapi_url="/api/openapi.json",
)

app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

# Global pipelines (loaded on first request)
_txt2img_pipeline = None
_img2img_pipeline = None
_current_model = None


class GenerateRequest(BaseModel):
    prompt: str = Field(..., description="Text prompt for image generation")
    negative_prompt: str = Field("", description="Negative prompt to avoid certain features")
    width: int = Field(512, ge=256, le=1024, description="Image width")
    height: int = Field(512, ge=256, le=1024, description="Image height")
    num_inference_steps: int = Field(1, ge=1, le=8, description="Number of inference steps (1-2 recommended for turbo models)")
    guidance_scale: float = Field(1.0, ge=0.0, le=20.0, description="Guidance scale (0.0-2.0 for turbo models)")
    seed: Optional[int] = Field(None, description="Random seed for reproducibility")
    model: str = Field(DEFAULT_MODEL, description="OpenVINO model to use")
    # Image-to-image support
    init_image: Optional[str] = Field(None, description="Base64 encoded input image for img2img mode")
    strength: float = Field(0.5, ge=0.0, le=1.0, description="How much to transform the input image (0=no change, 1=complete change)")


class GenerateResponse(BaseModel):
    image: str = Field(..., description="Base64 encoded PNG image")
    generation_time_ms: int = Field(..., description="Generation time in milliseconds")
    width: int
    height: int
    prompt: str
    seed: int
    mode: str = Field("txt2img", description="Generation mode: txt2img or img2img")


def _is_cuda() -> bool:
    return DEVICE == "cuda"


def load_txt2img_pipeline(model_id: str, use_local: bool = False):
    """Load txt2img pipeline — OpenVINO for CPU, diffusers AutoPipeline for CUDA"""
    global _txt2img_pipeline, _current_model

    if _txt2img_pipeline is not None and _current_model == model_id:
        return _txt2img_pipeline

    print(f"[{DEVICE.upper()}] Loading txt2img model: {model_id}...")
    start = time.time()

    if _is_cuda():
        from diffusers import AutoPipelineForText2Image
        _txt2img_pipeline = AutoPipelineForText2Image.from_pretrained(
            model_id,
            torch_dtype=torch.float16,
            variant="fp16",
        ).to("cuda")
    else:
        from optimum.intel.openvino import OVDiffusionPipeline
        _txt2img_pipeline = OVDiffusionPipeline.from_pretrained(
            model_id,
            local_files_only=use_local,
            ov_config={"CACHE_DIR": ""},
            device="CPU",
        )

    _current_model = model_id
    print(f"txt2img model loaded in {time.time() - start:.2f}s")
    return _txt2img_pipeline


def load_img2img_pipeline(model_id: str, use_local: bool = False):
    """Load img2img pipeline — OpenVINO for CPU, diffusers AutoPipeline for CUDA"""
    global _img2img_pipeline, _current_model

    if _img2img_pipeline is not None and _current_model == model_id:
        return _img2img_pipeline

    print(f"[{DEVICE.upper()}] Loading img2img model: {model_id}...")
    start = time.time()

    if _is_cuda():
        from diffusers import AutoPipelineForImage2Image
        _img2img_pipeline = AutoPipelineForImage2Image.from_pretrained(
            model_id,
            torch_dtype=torch.float16,
            variant="fp16",
        ).to("cuda")
    else:
        from optimum.intel.openvino.modeling_diffusion import OVStableDiffusionImg2ImgPipeline
        _img2img_pipeline = OVStableDiffusionImg2ImgPipeline.from_pretrained(
            model_id,
            local_files_only=use_local,
            ov_config={"CACHE_DIR": ""},
            device="CPU",
        )

    print(f"img2img model loaded in {time.time() - start:.2f}s")
    return _img2img_pipeline


@app.get("/")
async def root():
    return {"message": "FastSD CPU API - Ready", "status": "ok"}


@app.get("/api/")
async def api_root():
    return {"message": "FastSD CPU API - Ready", "status": "ok"}


@app.get("/health")
@app.get("/api/health")
async def health():
    return {
        "status": "ok",
        "txt2img_loaded": _txt2img_pipeline is not None,
        "img2img_loaded": _img2img_pipeline is not None,
        "current_model": _current_model,
    }


def _discover_cached_models() -> list[str]:
    models: set[str] = set()
    hf_home = Path(os.getenv("HF_HOME", str(Path.home() / ".cache" / "huggingface")))
    hub_dir = hf_home / "hub"

    if hub_dir.exists() and hub_dir.is_dir():
        for path in hub_dir.glob("models--*"):
            name = path.name
            if not name.startswith("models--"):
                continue
            repo = name[len("models--"):].replace("--", "/")
            if repo:
                models.add(repo)

    models.add(DEFAULT_MODEL_CPU)
    models.add(DEFAULT_MODEL_CUDA)
    if _current_model:
        models.add(_current_model)

    return sorted(models)


@app.get("/api/models")
async def models():
    return {
        "models": _discover_cached_models(),
        "current_model": _current_model,
        "default_model_cpu": DEFAULT_MODEL_CPU,
        "default_model_cuda": DEFAULT_MODEL_CUDA,
        "device": DEVICE,
    }


@app.post("/api/generate", response_model=GenerateResponse)
async def generate(request: GenerateRequest):
    """Generate an image from a text prompt, or transform an existing image (img2img)"""
    import torch
    from PIL import Image
    
    try:
        # Set seed
        seed = request.seed if request.seed is not None else int(time.time() * 1000) % (2**32)
        generator = torch.Generator("cuda" if _is_cuda() else "cpu").manual_seed(seed)
        
        # Check if this is img2img mode
        mode = "txt2img"
        init_image = None
        
        if request.init_image:
            mode = "img2img"
            # Decode base64 input image
            try:
                image_data = base64.b64decode(request.init_image)
                init_image = Image.open(io.BytesIO(image_data)).convert("RGB")
                # Resize to target dimensions
                init_image = init_image.resize((request.width, request.height), Image.Resampling.LANCZOS)
            except Exception as e:
                raise HTTPException(status_code=400, detail=f"Invalid init_image: {str(e)}")
        
        # Generate image
        start = time.time()
        
        if mode == "img2img" and init_image:
            # Load img2img pipeline
            pipeline = load_img2img_pipeline(request.model)
            
            # For img2img, ensure enough steps based on strength
            # effective_steps = int(num_steps * strength), so we need at least ceil(1/strength) steps
            min_steps_for_strength = max(1, int(1.0 / request.strength) + 1) if request.strength > 0 else 4
            img2img_steps = max(min_steps_for_strength, request.num_inference_steps)
            
            # Image-to-image generation using dedicated img2img pipeline
            result = pipeline(
                prompt=request.prompt,
                negative_prompt=request.negative_prompt if request.negative_prompt else None,
                image=init_image,
                strength=request.strength,
                num_inference_steps=img2img_steps,
                guidance_scale=request.guidance_scale,
                generator=generator,
            )
        else:
            # Load txt2img pipeline
            pipeline = load_txt2img_pipeline(request.model)
            
            # Text-to-image generation
            result = pipeline(
                prompt=request.prompt,
                negative_prompt=request.negative_prompt if request.negative_prompt else None,
                width=request.width,
                height=request.height,
                num_inference_steps=request.num_inference_steps,
                guidance_scale=request.guidance_scale,
                generator=generator,
            )
        
        elapsed_ms = int((time.time() - start) * 1000)
        
        # Convert to base64
        image = result.images[0]
        buffer = io.BytesIO()
        image.save(buffer, format="PNG")
        buffer.seek(0)
        image_base64 = base64.b64encode(buffer.read()).decode("utf-8")
        
        return GenerateResponse(
            image=image_base64,
            generation_time_ms=elapsed_ms,
            width=request.width,
            height=request.height,
            prompt=request.prompt,
            seed=seed,
            mode=mode,
        )
        
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))


@app.post("/api/generate-stream")
async def generate_stream(request: GenerateRequest):
    """Generate an image with SSE progress streaming. Supports both txt2img and img2img modes."""
    import torch
    import json
    import asyncio
    import threading
    import queue
    from fastapi.responses import StreamingResponse
    from PIL import Image
    
    # Queue for progress updates from the generation thread
    progress_queue = queue.Queue()
    result_holder = {"result": None, "error": None}
    
    # Check if this is img2img mode
    mode = "img2img" if request.init_image else "txt2img"
    
    def generate_in_thread():
        """Run generation in a separate thread so we can send progress"""
        try:
            # Set seed
            seed = request.seed if request.seed is not None else int(time.time() * 1000) % (2**32)
            generator = torch.Generator("cuda" if _is_cuda() else "cpu").manual_seed(seed)
            
            # Prepare init_image for img2img mode
            init_image = None
            if request.init_image:
                try:
                    image_data = base64.b64decode(request.init_image)
                    init_image = Image.open(io.BytesIO(image_data)).convert("RGB")
                    init_image = init_image.resize((request.width, request.height), Image.Resampling.LANCZOS)
                    progress_queue.put({"progress": 10, "message": "Source image loaded"})
                except Exception as e:
                    result_holder["error"] = f"Invalid init_image: {str(e)}"
                    progress_queue.put({"error": result_holder["error"], "done": True})
                    return
            
            # Load the appropriate pipeline
            if init_image:
                pipeline = load_img2img_pipeline(request.model)
                progress_queue.put({"progress": 15, "message": "img2img model ready", "mode": mode})
            else:
                pipeline = load_txt2img_pipeline(request.model)
                progress_queue.put({"progress": 15, "message": "Model ready", "mode": mode})
            
            progress_queue.put({"progress": 20, "message": f"Starting {mode} generation...", "mode": mode})
            
            # Generate image with callback for progress
            start = time.time()
            
            # For img2img with low strength, we need more steps to ensure at least 1 effective step
            if init_image and request.strength > 0:
                min_steps_for_strength = max(1, int(1.0 / request.strength) + 1)
                total_steps = max(min_steps_for_strength, request.num_inference_steps)
            else:
                total_steps = request.num_inference_steps
            
            # Custom callback to report progress
            def progress_callback(pipe, step, timestep, callback_kwargs):
                # Calculate progress (20-90% range for generation)
                pct = 20 + int((step + 1) / total_steps * 70)
                progress_queue.put({
                    "progress": pct,
                    "message": f"Step {step + 1}/{total_steps}",
                    "step": step + 1,
                    "total_steps": total_steps,
                    "mode": mode
                })
                return callback_kwargs
            
            if init_image:
                # Image-to-image generation
                result = pipeline(
                    prompt=request.prompt,
                    negative_prompt=request.negative_prompt if request.negative_prompt else None,
                    image=init_image,
                    strength=request.strength,
                    num_inference_steps=total_steps,
                    guidance_scale=request.guidance_scale,
                    generator=generator,
                    callback_on_step_end=progress_callback,
                )
            else:
                # Text-to-image generation
                result = pipeline(
                    prompt=request.prompt,
                    negative_prompt=request.negative_prompt if request.negative_prompt else None,
                    width=request.width,
                    height=request.height,
                    num_inference_steps=request.num_inference_steps,
                    guidance_scale=request.guidance_scale,
                    generator=generator,
                    callback_on_step_end=progress_callback,
                )
            
            progress_queue.put({"progress": 92, "message": "Encoding image..."})
            
            elapsed_ms = int((time.time() - start) * 1000)
            
            # Convert to base64
            image = result.images[0]
            buffer = io.BytesIO()
            image.save(buffer, format="PNG")
            buffer.seek(0)
            image_base64 = base64.b64encode(buffer.read()).decode("utf-8")
            
            result_holder["result"] = {
                "image": image_base64,
                "generation_time_ms": elapsed_ms,
                "width": request.width,
                "height": request.height,
                "prompt": request.prompt,
                "seed": seed,
                "mode": mode,
            }
            progress_queue.put({"done": True})
            
        except Exception as e:
            result_holder["error"] = str(e)
            progress_queue.put({"error": str(e), "done": True})
    
    async def event_generator():
        try:
            # Send initial progress
            yield f"data: {json.dumps({'progress': 5, 'message': 'Loading model...'})}\n\n"
            
            # Start generation thread
            thread = threading.Thread(target=generate_in_thread)
            thread.start()
            
            # Poll queue for progress updates
            while True:
                try:
                    # Non-blocking check with small timeout
                    update = progress_queue.get(timeout=0.1)
                    
                    if update.get("done"):
                        # Generation complete
                        if result_holder["error"]:
                            yield f"data: {json.dumps({'error': result_holder['error'], 'progress': 0})}\n\n"
                        else:
                            yield f"data: {json.dumps({'progress': 100, 'message': 'Complete!'})}\n\n"
                            # Send final result
                            final = result_holder["result"]
                            final["progress"] = 100
                            final["done"] = True
                            yield f"data: {json.dumps(final)}\n\n"
                        break
                    else:
                        # Progress update
                        yield f"data: {json.dumps(update)}\n\n"
                        
                except queue.Empty:
                    # No update yet, just continue polling
                    await asyncio.sleep(0.05)
                    continue
            
            thread.join()
            
        except Exception as e:
            yield f"data: {json.dumps({'error': str(e), 'progress': 0})}\n\n"
    
    return StreamingResponse(
        event_generator(),
        media_type="text/event-stream",
        headers={
            "Cache-Control": "no-cache",
            "Connection": "keep-alive",
            "X-Accel-Buffering": "no",
        }
    )


def main():
    global DEVICE, DEFAULT_MODEL

    parser = ArgumentParser(description="FastSD CPU/GPU Minimal API Server")
    parser.add_argument("--host", default="0.0.0.0", help="Host to bind to")
    parser.add_argument("--port", type=int, default=8888, help="Port to bind to")
    parser.add_argument("--preload", action="store_true", help="Preload the default model on startup")
    parser.add_argument(
        "--device",
        default="auto",
        choices=["auto", "cpu", "cuda"],
        help="Device to use: auto (detect), cpu (OpenVINO), cuda (diffusers fp16)",
    )
    parser.add_argument("--model", default=None, help="Override model to use/preload")
    args = parser.parse_args()

    # Resolve device
    if args.device == "auto":
        DEVICE = "cuda" if torch.cuda.is_available() else "cpu"
    else:
        DEVICE = args.device

    if DEVICE == "cuda" and not torch.cuda.is_available():
        print("WARNING: --device cuda requested but CUDA is not available; falling back to cpu")
        DEVICE = "cpu"

    DEFAULT_MODEL = DEFAULT_MODEL_CUDA if DEVICE == "cuda" else DEFAULT_MODEL_CPU
    model = args.model or DEFAULT_MODEL

    print(f"Device : {DEVICE.upper()}")
    print(f"Model  : {model}")

    if args.preload:
        print("Preloading txt2img model...")
        load_txt2img_pipeline(model)

    print(f"Starting FastSD API on http://{args.host}:{args.port}")
    print(f"API docs: http://{args.host}:{args.port}/api/docs")

    uvicorn.run(app, host=args.host, port=args.port)


if __name__ == "__main__":
    main()
