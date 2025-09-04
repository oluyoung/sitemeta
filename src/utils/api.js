import apiFetch from '@wordpress/api-fetch';
import { API_ROOT } from './constants';

export const fetchFields = () => apiFetch({ path: `${API_ROOT}/fields` });

export const createField = (payload) =>
  apiFetch({ path: `${API_ROOT}/fields`, method: 'POST', data: payload });

export const updateField = (id, payload) =>
  apiFetch({ path: `${API_ROOT}/fields/${encodeURIComponent(id)}`, method: 'PUT', data: payload });

export const deleteField = (id) =>
  apiFetch({ path: `${API_ROOT}/fields/${encodeURIComponent(id)}`, method: 'DELETE' });
