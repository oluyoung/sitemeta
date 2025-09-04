import { Fragment } from "@wordpress/element";
import {
  Button,
  Notice,
} from "@wordpress/components";
import { __ } from "@wordpress/i18n";
import Modal from "react-modal";
import JSONEditor from "jsoneditor";
import "jsoneditor/dist/jsoneditor.css";

const customStyles = {
  content: {
    top: "50%",
    left: "50%",
    right: "auto",
    bottom: "auto",
    marginRight: "-50%",
    transform: "translate(-50%, -50%)",
    height: 'calc(100vh - 45vh)',
  },
};

Modal.setAppElement("#site-meta-admin-app");

export function JsonModal({
  isOpen,
  onClose,
  initialValue,
  onSave,
}) {
  const divRef = wp.element.useRef(null);
  const editorRef = wp.element.useRef(null);
  const valueStr = wp.element.useMemo(() => {
    return typeof initialValue === "string"
      ? JSON.parse(initialValue)
      : initialValue ?? {};
  }, [initialValue]);

  const [error, setError] = wp.element.useState("");

  wp.element.useEffect(() => {
    if (isOpen) {
      setError("");

      if (!editorRef.current) {
        editorRef.current = new JSONEditor(divRef.current, {
          mode: "text",
          search: true,
          modes: ["code", "text", "tree", "view"], // allowed modes
        });
      }
      editorRef.current.set(valueStr);
    }
  }, [isOpen, valueStr]);

  const handleSave = () => {
    try {
      const parsed = editorRef.current.get();
      onSave(parsed);
    } catch (e) {
      setError(__("Invalid JSON. Please fix and try again.", "site-meta"));
      return;
    }
    onClose();
  };

  function afterOpenModal() {
    // references are now sync'd and can be accessed.
  }

  // if (!isOpen) return null;

  return (
    <Modal
      isOpen
      onAfterOpen={afterOpenModal}
      onRequestClose={onClose}
      style={{...customStyles, overlay: { ...customStyles.overlay, display: isOpen ? 'block' : 'none' },  content: { ...customStyles.content, display: isOpen ? 'block' : 'none' }}}
      contentLabel={__('Edit JSON', 'site-meta')}
    >
      <div style={{ minWidth: '640px', height: '100%' }}>
        {error ? (
          <Notice
            status="error"
            isDismissible={true}
            onRemove={() => setError("")}
          >
            {error}
          </Notice>
        ) : null}
        <div ref={divRef} id="jsoneditor" style={{ width: '100%', height: '90%' }} />
        <div
          style={{
            display: "flex",
            justifyContent: "flex-end",
            gap: 8,
            marginTop: 16,
          }}
        >
          <Button variant="secondary" onClick={onClose}>
            {__("Cancel", "site-meta")}
          </Button>
          <Button variant="primary" onClick={handleSave}>
            {__("Save", "site-meta")}
          </Button>
        </div>
      </div>
    </Modal>
  );
}
