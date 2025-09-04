import { Fragment } from "@wordpress/element";
import {
  // Modal,
  Button,
  TextareaControl,
  Notice,
} from "@wordpress/components";
import { __ } from "@wordpress/i18n";
import Modal from "react-modal";

const customStyles = {
  content: {
    top: "50%",
    left: "50%",
    right: "auto",
    bottom: "auto",
    marginRight: "-50%",
    transform: "translate(-50%, -50%)",
  },
};

Modal.setAppElement("#site-meta-admin-app");

export function JsonListModal({
  isOpen,
  onClose,
  initialValue,
  onSave,
  mode = "json",
}) {
  const valueStr =
    typeof initialValue === "string"
      ? initialValue
      : JSON.stringify(initialValue ?? (mode === "list" ? [] : {}), null, 2);

  const [value, setValue] = wp.element.useState(valueStr);
  const [error, setError] = wp.element.useState("");

  wp.element.useEffect(() => {
    setValue(valueStr);
    setError("");
  }, [isOpen, valueStr]);

  const handleSave = () => {
    if (mode === "json" || mode === "keyvalue") {
      try {
        const parsed = JSON.parse(value);
        onSave(parsed);
      } catch (e) {
        setError(__("Invalid JSON. Please fix and try again.", "site-meta"));
        return;
      }
    } else if (mode === "list") {
      // list mode: one item per line -> array
      const arr = value
        .split("\n")
        .map((s) => s.trim())
        .filter(Boolean);
      onSave(arr);
    } else {
      onSave(value);
    }
    onClose();
  };

  function afterOpenModal() {
    // references are now sync'd and can be accessed.
  }

  if (!isOpen) return null;

  return (
    <Modal
      isOpen={isOpen}
      onAfterOpen={afterOpenModal}
      onRequestClose={onClose}
      style={customStyles}
      contentLabel={ mode === 'json' ? __('Edit JSON', 'site-meta') : (mode === 'list' ? __('Edit List', 'site-meta') : __('Edit', 'site-meta')) }
    >
      <div style={{ minWidth: '640px' }}>
        {error && (
          <Notice
            status="error"
            isDismissible={true}
            onRemove={() => setError("")}
          >
            {error}
          </Notice>
        )}
        <TextareaControl
          label={
            mode === "list"
              ? __("List items (one per line)", "site-meta")
              : __("Value", "site-meta")
          }
          value={value}
          onChange={setValue}
          rows={12}
        />
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
