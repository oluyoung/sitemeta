import React from "react";
import domReady from "@wordpress/dom-ready";
import apiFetch from "@wordpress/api-fetch";
import { useEffect, useMemo, useState, createRoot } from "@wordpress/element";
import {
  Button,
  Flex,
  FlexItem,
  Notice,
  PanelBody,
  Spinner,
  TextControl,
  __experimentalHStack as HStack,
  __experimentalVStack as VStack,
} from "@wordpress/components";
import { __ } from "@wordpress/i18n";
import { EmptyState } from "./EmptyState";
import { FieldEditor } from "./FieldEditor";
import { FieldsTable } from "./FieldsTable";
import {
  createField,
  updateField,
  deleteField,
} from "./utils/api";
import { useFields } from "./hooks/useFields";
import "./admin.css";

apiFetch.use(apiFetch.createNonceMiddleware(window.SiteMetaSettings?.nonce));

export const App = () => {
  const { fields, loading, upsertField, removeField } = useFields();
  const [error, setError] = useState("");
  const [editing, setEditing] = useState(null);
  const [saving, setSaving] = useState(false);
  const [query, setQuery] = useState("");

  const filtered = query
    ? fields.filter(
        (it) =>
          it.field_id?.toLowerCase().includes(query.toLowerCase()) ||
          it.type?.toLowerCase().includes(query.toLowerCase())
      )
    : fields;

  const startNew = () => {
    setEditing({ field_id: "", type: "text", content: "" });
  };

  const onSave = async () => {
    if (!editing?.field_id) {
      setError(__("ID is required", "site-meta"));
      return;
    }

    const hasValue = (() => {
      if (editing.type === "text")
        return !!(editing.content && editing.content.toString().trim());
      if (editing.type === "image") return !!editing.content;
      if (editing.type === "gallery")
        return Array.isArray(editing.content) && editing.content.length > 0;
      if (editing.type === "list")
        return Array.isArray(editing.content) && editing.content.length > 0;
      if (editing.type === "json" || editing.type === "keyvalue") {
        const isValid = Array.isArray(editing.json_content) ? !!editing.json_content.length : !!Object.keys(editing.content).length;
        return isValid;
      }
      return false;
    })();
    if (!hasValue) {
      setError(
        __(
          "A value is required for this field type before saving.",
          "site-meta"
        )
      );
      return;
    }

    setSaving(true);
    setError("");
    try {
      const existing = fields.find((f) => f.field_id === editing.field_id);
      const payload = editing;
      let saved;
      if (existing) {
        saved = await updateField(editing.field_id, payload);
      } else {
        saved = await createField(payload);
      }
      upsertField(saved);
      setEditing(null);
    } catch (e) {
      setError(e?.message || "Error saving");
    } finally {
      setSaving(false);
    }
  };

  const onDelete = async () => {
    if (!editing?.field_id) return;
    setSaving(true);
    try {
      await deleteField(editing.field_id);
      removeField(editing.field_id);
      setEditing(null);
    } catch (e) {
      setError(e?.message || "Error deleting");
    } finally {
      setSaving(false);
    }
  };

  return (
    <VStack className="sitemeta-app" spacing="16">
      <Flex justify="space-between" align="center">
        <FlexItem>
          <h1>{__("Site Meta", "site-meta")}</h1>
        </FlexItem>
        <FlexItem>
          <HStack spacing="8">
            <TextControl
              placeholder={__("Search…", "site-meta")}
              value={query}
              onChange={setQuery}
            />
            <Button isPrimary onClick={startNew}>
              {__("Add Field", "site-meta")}
            </Button>
          </HStack>
        </FlexItem>
      </Flex>

      {error && (
        <Notice status="error" isDismissible onRemove={() => setError("")}>
          {error}
        </Notice>
      )}

      {loading ? (
        <Spinner />
      ) : filtered.length ? (
        <FieldsTable items={filtered} onEdit={setEditing} />
      ) : (
        <EmptyState />
      )}

      {editing && (
        <PanelBody title={__("Add Field", "site-meta")} initialOpen>
          <FieldEditor
            field={editing}
            onChange={setEditing}
            onSave={onSave}
            onDelete={onDelete}
            saving={saving}
          />
        </PanelBody>
      )}
    </VStack>
  );
};

domReady(() => {
  const root = createRoot(document.getElementById("site-meta-admin-app"));
  root.render(<App />);
});
