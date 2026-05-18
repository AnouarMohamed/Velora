/** Limite stricte 2 Mo (alignée PHP et API). */
export const MAX_IMAGE_MB = 2;
const MAX_BYTES = Math.floor(MAX_IMAGE_MB * 1024 * 1024 * 0.92);
const MAX_WIDTH = 1920;
export async function compressImageForUpload(file) {
    if (!file.type.startsWith('image/')) {
        return file;
    }
    if (file.size <= MAX_BYTES) {
        return file;
    }
    const bitmap = await createImageBitmap(file);
    let width = bitmap.width;
    let height = bitmap.height;
    if (width > MAX_WIDTH) {
        height = Math.round((height * MAX_WIDTH) / width);
        width = MAX_WIDTH;
    }
    const canvas = document.createElement('canvas');
    canvas.width = width;
    canvas.height = height;
    const ctx = canvas.getContext('2d');
    if (!ctx) {
        bitmap.close();
        throw new Error('Compression impossible.');
    }
    ctx.drawImage(bitmap, 0, 0, width, height);
    bitmap.close();
    let quality = 0.85;
    let blob = null;
    while (quality >= 0.4) {
        blob = await new Promise((resolve) => {
            canvas.toBlob((b) => resolve(b), 'image/jpeg', quality);
        });
        if (blob && blob.size <= MAX_BYTES)
            break;
        quality -= 0.08;
    }
    if (!blob || blob.size > MAX_BYTES) {
        throw new Error('IMAGE_TOO_LARGE');
    }
    const baseName = file.name.replace(/\.[^.]+$/i, '') || 'image';
    return new File([blob], `${baseName}.jpg`, { type: 'image/jpeg', lastModified: Date.now() });
}
