import { useEffect, useState, useRef } from '@wordpress/element';
import * as api from '../utils/api';

export const useFields = () => {
  const [fields, setFields] = useState([]);
  const [loading, setLoading] = useState(true);
  const mounted = useRef(true);

  useEffect(() => {
    mounted.current = true;
    setLoading(true);
    api.fetchFields()
      .then((res) => {
        if (!mounted.current) return;
        setFields(Array.isArray(res) ? res : []);
      })
      .catch(() => {
        if (!mounted.current) return;
        setFields([]);
      })
      .finally(() => mounted.current && setLoading(false));

    return () => {
      mounted.current = false;
    };
  }, []);

  const upsertField = (saved) => {
    setFields((prev) => {
      const exists = prev.some((f) => f.field_id === saved.field_id);
      if (exists) return prev.map((f) => (f.field_id === saved.field_id ? saved : f));
      return [...prev, saved];
    });
  };

  const removeField = (field_id) => {
    setFields((prev) => prev.filter((f) => f.field_id !== field_id));
  };

  return {
    fields,
    setFields,
    loading,
    upsertField,
    removeField,
  };
};
