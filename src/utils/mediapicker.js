let frame = undefined;

export function openMediaLibrary({ multiple = false, title = 'Select image(s)', selectedIds } = {}) {
  return new Promise((resolve, reject) => {
    if (!window.wp || !window.wp.media) {
      reject(new Error('wp.media is not available'));
      return;
    }

    frame = window.wp.media({
      title,
      library: { type: 'image' },
      button: { text: 'Use selected' },
      multiple,
    });

    frame.on('open', function() {
      const selection = frame.state().get('selection');
      
      if (Array.isArray(selectedIds)) {
        selectedIds.forEach((id) => {
          selection.add(wp.media.attachment(id));
        });
      } else {
        let id = selectedIds;
        if (typeof selectedIds === 'string') id = parseInt(selectedIds);
        selection.add(wp.media.attachment(id));
      }
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
