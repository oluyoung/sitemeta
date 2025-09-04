import domReady from '@wordpress/dom-ready';
import apiFetch from '@wordpress/api-fetch';
import { createRoot, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import {
	Button,
	Flex,
	FlexItem,
	HStack,
	Notice,
	PanelBody,
	Spinner,
	TextControl,
	VStack,
} from '@wordpress/components';
import { fetchFields, createField, updateField, deleteField } from './utils/api';
import { useFields } from './hooks/useFields';
import FieldsTable from './FieldsTable';
import FieldEditor from './FieldEditor';
import "./admin.css";

if (window.SiteMetaSettings?.nonce) {
	apiFetch.use(apiFetch.createNonceMiddleware(window.SiteMetaSettings.nonce));
}

function AppRoot() {
	const { fields, loading, upsertField, removeField } = useFields();
	const [editing, setEditing] = useState(null);
	const [saving, setSaving] = useState(false);
	const [query, setQuery] = useState('');
	const [error, setError] = useState('');

	const filtered = (query ? fields.filter((it) => it.id?.toLowerCase().includes(query.toLowerCase()) || it.type?.toLowerCase().includes(query.toLowerCase())) : fields);

	const startNew = () => setEditing({ id: '', type: 'text', content: '' });

	const onSave = async () => {
		if (!editing?.id) {
			setError(__('ID is required', 'site-meta'));
			return;
		}

		const hasValue = (() => {
			if (editing.type === 'text') return !!(editing.content && editing.content.toString().trim());
			if (editing.type === 'image') return !!editing.content;
			if (editing.type === 'gallery') return Array.isArray(editing.content) && editing.content.length > 0;
			if (editing.type === 'list') return Array.isArray(editing.content) && editing.content.length > 0;
			if (editing.type === 'json' || editing.type === 'keyvalue') return editing.content && Object.keys(editing.content).length > 0;
			return false;
		})();
		if (!hasValue) {
			setError(__('A value is required for this field type before saving.', 'site-meta'));
			return;
		}

		setSaving(true);
		setError('');
		try {
			const existing = fields.find((f) => f.id === editing.id);
			const payload = editing;
			let saved;
			if (existing) {
				saved = await updateField(editing.id, payload);
			} else {
				saved = await createField(payload);
			}
			upsertField(saved);
			setEditing(null);
		} catch (e) {
			setError(e?.message || 'Error saving');
		} finally {
			setSaving(false);
		}
	};

	const onDelete = async () => {
		if (!editing?.id) return;
		setSaving(true);
		try {
			await deleteField(editing.id);
			removeField(editing.id);
			setEditing(null);
		} catch (e) {
			setError(e?.message || 'Error deleting');
		} finally {
			setSaving(false);
		}
	};

	return (
		<VStack spacing="16" className="sitemeta-app" style={{ padding: 16 }}>
			<Flex justify="space-between" align="center">
				<FlexItem><h1>{ __('Site Meta', 'site-meta') }</h1></FlexItem>
				<FlexItem>
					<HStack spacing="8">
						<TextControl placeholder={ __('Search…', 'site-meta') } value={ query } onChange={ setQuery } />
						<Button isPrimary onClick={ startNew }>{ __('Add Field', 'site-meta') }</Button>
					</HStack>
				</FlexItem>
			</Flex>

			{/* { error && <Notice status="error" isDismissible onRemove={() => setError('')}>{ error }</Notice> }

			{ loading ? (
				<Spinner />
			) : filtered.length ? (
				<FieldsTable items={ filtered } onEdit={ setEditing } />
			) : (
				<div>{ __('No fields yet. Click "Add Field" to create one.', 'site-meta') }</div>
			) }

			{ editing && (
				<PanelBody title={ __('Edit Field', 'site-meta') } initialOpen>
					<FieldEditor
						field={ editing }
						onChange={ setEditing }
						onSave={ onSave }
						onDelete={ onDelete }
						saving={ saving }
					/>
				</PanelBody>
			) } */}
		</VStack>
	);
}

domReady(() => {
	console.log('--')
	const root = createRoot(document.getElementById("site-meta-admin-app"));
	root.render(<AppRoot />);
});