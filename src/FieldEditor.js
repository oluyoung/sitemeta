import { useMemo, useState } from '@wordpress/element';
import {
  Button,
  Card,
  CardBody,
  CardHeader,
  Flex,
  FlexItem,
  SelectControl,
  TextControl,
  TextareaControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { TYPES } from './utils/constants';
import { JsonModal } from './JSONModal';
import { openMediaLibrary } from './utils/mediapicker';

export function FieldEditor({ field, onChange, onSave, onDelete, saving }) {
  const [modal, setModal] = useState({ open: false, initial: null });

  const { field_id = '', type = 'text', content, json_content } = field || {};

  // basic validation: id and content required before save
  const isContentEmpty = () => {
    if (type === 'text' || type === 'image') return !content || (typeof content === 'string' && content.trim() === '');
    if (type === 'json') return !json_content || (Object.keys(json_content || {}).length === 0);
    if (type === 'gallery') return !Array.isArray(content) || content.length === 0;
    return false;
  };

  const canSave = field_id && field_id.trim().length > 0 && !isContentEmpty();

  const openJsonModal = () => {
    setModal({ open: true, initial: json_content });
  };

  const onModalSave = (val) => {
    onChange(prev => ({ ...prev, json_content: val }));
    setModal({ open: false, initial: null });
  };

  const onPickImage = async (multiple = false) => {
    try {
      const selection = await openMediaLibrary({ multiple, title: multiple ? 'Select gallery' : 'Select image' });
      if (!selection) return;
      if (multiple) {
        // store as array of attachment ids
        onChange(prev => ({ ...prev, content: selection.map((s) => s.id) }));
      } else {
        onChange(prev => ({ ...prev, content: selection[0].id }));
      }
    } catch (e) {
      // ignore or display UI notice externally
      console.error(e);
    }
  };

  const contentEditor = useMemo(() => {
    switch (type) {
      case 'text':
        return (
          <TextareaControl
            label={ __('Content (text)', 'site-meta') }
            value={ content ?? '' }
            onChange={ (val) => onChange(prev => ({ ...prev, content: val })) }
          />
        );
      case 'json':
        return (
          <div>
            <Button onClick={() => openJsonModal('json')}>{ __('Edit JSON', 'site-meta') }</Button>
            <div style={{ marginTop: 8, fontSize: 13 }}>{ json_content ? '[JSON present]' : __('No JSON set', 'site-meta') }</div>
          </div>
        );
      case 'image':
        return (
          <div>
            <Button variant='secondary' onClick={() => onPickImage(false)}>{ __('Select Image', 'site-meta') }</Button>
            <div style={{ marginTop: 8, fontSize: 13 }}>{ content ? `ID: ${content}` : __('No image selected', 'site-meta') }</div>
          </div>
        );
      case 'gallery':
        return (
          <div>
            <Button variant='secondary' onClick={() => onPickImage(true)}>{ __('Select Gallery', 'site-meta') }</Button>
            <div style={{ marginTop: 8, fontSize: 13 }}>{ Array.isArray(content) ? `${content.length} images` : __('No images', 'site-meta') }</div>
          </div>
        );
      default:
        return null;
    }
  }, [type, content, field, onChange]);

  return (
    <>
      <Card isElevated className="sitemeta-field-card">
        <CardHeader>
          <Flex justify="space-between" align="center">
            <FlexItem>
              <strong>{ field_id || __('(new field)', 'site-meta') }</strong>
            </FlexItem>
            <FlexItem>
              <Button
                isPrimary
                onClick={ onSave }
                disabled={ saving || !canSave }
              >
                { saving ? __('Saving…', 'site-meta') : __('Save', 'site-meta') }
              </Button>
              { field_id && (
                <Button
                  isDestructive
                  onClick={ onDelete }
                  disabled={ saving }
                  style={{ marginLeft: 8 }}
                >
                  { __('Delete', 'site-meta') }
                </Button>
              ) }
            </FlexItem>
          </Flex>
        </CardHeader>

        <CardBody>
          <div style={{ marginBottom: 12 }}>
            <TextControl
              label={ __('Field ID (unique, a-z0-9_-)', 'site-meta') }
              value={ field_id }
              onChange={ (val) => onChange(prev => ({ ...prev, field_id: val.replace(/[^a-z0-9_-]/gi, '').toLowerCase() })) }
            />
          </div>

          <div style={{ marginBottom: 12 }}>
            <SelectControl
              label={ __('Type', 'site-meta') }
              value={ type }
              options={ TYPES }
              onChange={ (val) => onChange(prev => ({ ...prev, type: val, content: (val === 'list' ? [] : (val === 'gallery' ? [] : undefined)) })) }
            />
          </div>

          { contentEditor }
        </CardBody>
      </Card>

      <JsonModal
        isOpen={modal.open}
        onClose={() => setModal({ open: false, })}
        initialValue={modal.initial}
        mode={modal.mode}
        onSave={onModalSave}
      />
    </>
  );
}
