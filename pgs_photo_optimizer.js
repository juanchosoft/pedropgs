/**
 * PGS Photo Optimizer: utility for the existing camera/report scripts.
 * Integration point: call PGSPhotoOptimizer.optimizeCanvas(canvas) after
 * drawing the camera frame and BEFORE canvas.toDataURL()/toBlob().
 * No images are uploaded by this file, and no network/legacy handlers change.
 */
(function (window) {
  'use strict';
  const MAX_EDGE = 1280;
  const QUALITY = 0.72;

  function targetSize(width, height, maxEdge) {
    const scale = Math.min(1, maxEdge / Math.max(width, height));
    return { width: Math.max(1, Math.round(width * scale)), height: Math.max(1, Math.round(height * scale)) };
  }

  function optimizeCanvas(source, opts) {
    opts = opts || {};
    const maxEdge = Math.max(320, Math.min(2560, opts.maxEdge || MAX_EDGE));
    const width = source.width, height = source.height;
    if (!width || !height) throw new Error('The photo is empty.');
    const size = targetSize(width, height, maxEdge);
    const output = document.createElement('canvas');
    output.width = size.width;
    output.height = size.height;
    const ctx = output.getContext('2d', { alpha: false });
    if (!ctx) throw new Error('Could not process image.');
    ctx.imageSmoothingEnabled = true;
    ctx.imageSmoothingQuality = 'high';
    ctx.drawImage(source, 0, 0, size.width, size.height);
    return output;
  }

  function canvasToBlob(canvas, quality) {
    return new Promise(function (resolve, reject) {
      canvas.toBlob(function (blob) {
        if (blob) resolve(blob);
        else reject(new Error('Could not compress photo.'));
      }, 'image/jpeg', quality || QUALITY);
    });
  }

  function blobToImage(blob) {
    return new Promise(function(resolve, reject){
      const url = URL.createObjectURL(blob);
      const image = new Image();
      image.onload = function(){ resolve({image, url}); };
      image.onerror = function(){ URL.revokeObjectURL(url); reject(new Error('Invalid photo.')); };
      image.src = url;
    });
  }

  async function optimizeFile(file, opts) {
    opts = opts || {};
    if (!file || !file.type.startsWith('image/')) throw new Error('Select an image.');
    const decoded = await blobToImage(file);
    try {
      const maxEdge = Math.max(320, Math.min(2560, opts.maxEdge || MAX_EDGE));
      const size = targetSize(decoded.image.naturalWidth, decoded.image.naturalHeight, maxEdge);
      const canvas = document.createElement('canvas');
      canvas.width = size.width; canvas.height = size.height;
      const context = canvas.getContext('2d', {alpha:false});
      context.fillStyle = '#fff';
      context.fillRect(0, 0, canvas.width, canvas.height);
      context.drawImage(decoded.image, 0, 0, canvas.width, canvas.height);
      return canvasToBlob(canvas, opts.quality);
    } finally { URL.revokeObjectURL(decoded.url); }
  }

  window.PGSPhotoOptimizer = Object.freeze({
    optimizeCanvas, canvasToBlob, optimizeFile,
    // Usage with current base64 backend: optimized.toDataURL('image/jpeg', 0.72)
    defaults: Object.freeze({maxEdge: MAX_EDGE, quality: QUALITY})
  });
})(window);
