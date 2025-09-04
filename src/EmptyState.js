import React from 'react';
import {
  Card,
  CardBody,
} from "@wordpress/components";
import { __ } from "@wordpress/i18n";

export const EmptyState = () => (
  <Card>
    <CardBody>
      <p>
        {__('No fields yet. Click "Add Field" to create one.', "site-meta")}
      </p>
    </CardBody>
  </Card>
);