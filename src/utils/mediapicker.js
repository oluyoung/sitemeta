// Utility to open wp.media and return selected attachment ids (or single id)
export function openMediaLibrary({ multiple = false, title = 'Select image(s)' } = {}) {
  return new Promise((resolve, reject) => {
    if (!window.wp || !window.wp.media) {
      reject(new Error('wp.media is not available'));
      return;
    }

    const frame = window.wp.media({
      title,
      library: { type: 'image' },
      button: { text: 'Use selected' },
      multiple,
    });

    frame.on('select', () => {
      const selection = frame.state().get('selection').toArray().map((att) => att.toJSON());
      // return array of attachment objects
      resolve(selection);
    });

    frame.on('cancel', () => {
      resolve(null);
    });

    frame.open();
  });
}
