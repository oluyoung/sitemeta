import { Button, Card, CardBody } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

export function FieldsTable({ items = [], onEdit }) {
  return (
    <Card>
      <CardBody>
        <table className="sitemeta-table" style={{ width: '100%', borderCollapse: 'collapse' }}>
          <thead>
            <tr>
              <th>{ __('Field ID', 'site-meta') }</th>
              <th>{ __('Type', 'site-meta') }</th>
              <th>{ __('Preview', 'site-meta') }</th>
              <th style={{ width: 120 }}></th>
            </tr>
          </thead>
          <tbody>
            {items.map((it) => {
              let preview = '';
              switch (it.type) {
                case 'text': preview = (it.content ?? '').toString().slice(0, 60); break;
                case 'json': preview = '[JSON]'; break;
                case 'image': preview = it.content ? `ID ${it.content}` : ''; break;
                case 'gallery': preview = Array.isArray(it.json_content) ? `${it.json_content.length} images` : '[gallery]'; break;
              }
              return (
                <tr key={ it.field_id }>
                  <td><code>{ it.field_id }</code></td>
                  <td>{ it.type }</td>
                  <td>{ preview }</td>
                  <td>
                    <Button variant="secondary" onClick={() => onEdit(it)}>{ __('Edit', 'site-meta') }</Button>
                  </td>
                </tr>
              );
            })}
          </tbody>
        </table>
      </CardBody>
    </Card>
  );
}
