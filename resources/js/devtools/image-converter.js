export const CONVERTIBLE_FORMATS = ['image/png', 'image/jpeg', 'image/webp'];

const FORMAT_LABELS = {
  'image/png': 'PNG',
  'image/jpeg': 'JPEG',
  'image/webp': 'WebP',
};

/** @param {string} mime */
export function formatLabel(mime) {
  return FORMAT_LABELS[mime] ?? (mime.split('/')[1] || mime).toUpperCase();
}

/** @param {string} dataUrl */
export function mimeFromDataUrl(dataUrl) {
  const match = dataUrl.match(/^data:(image\/[^;]+);/i);
  return match ? match[1].toLowerCase() : '';
}

/**
 * Build format dropdown from upload MIME — known types use defaults; others are added as placeholders.
 * @param {string} uploadType
 * @returns {{ format: string, formats: string[] }}
 */
export function resolveImageFormats(uploadType) {
  const formats = [...CONVERTIBLE_FORMATS];
  const type = uploadType.startsWith('image/') ? uploadType.toLowerCase() : '';
  if (type && !formats.includes(type)) {
    formats.unshift(type);
  }
  return { format: type || 'image/png', formats };
}

/** Canvas can export to this MIME type. */
export function isConvertibleFormat(mime) {
  return CONVERTIBLE_FORMATS.includes(mime);
}

/**
 * Convert when output is PNG/JPEG/WebP. Source-only placeholder (e.g. GIF selected) cannot export.
 * @param {string} selectedFormat
 * @param {string} uploadType
 */
export function canConvertImage(selectedFormat, uploadType) {
  if (!isConvertibleFormat(selectedFormat)) {
    return false;
  }
  if (selectedFormat === uploadType && !isConvertibleFormat(uploadType)) {
    return false;
  }
  return true;
}

/** @param {string} mime */
export function extensionForMime(mime) {
  const map = { 'image/png': 'png', 'image/jpeg': 'jpg', 'image/webp': 'webp' };
  if (map[mime]) {
    return map[mime];
  }
  const sub = mime.split('/')[1] || 'img';
  return sub.replace(/[^a-z0-9]/gi, '') || 'img';
}
