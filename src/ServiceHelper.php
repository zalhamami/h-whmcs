<?php
namespace Hostinger\WhmcsModule;

use WHMCS\Database\Capsule;


class ServiceHelper
{
    /**
     * Retrieve the value of a given custom field for a specific service.
     */
    public static function getCustomFieldValue($params, $fieldName)
    {
        $serviceId = $params['serviceid'];
        $productId = $params['pid'];
        // Find the custom field definition for this product by name
        $field = Capsule::table('tblcustomfields')
            ->where('type', 'product')
            ->where('relid', $productId)
            ->where('fieldname', $fieldName)
            ->first();
        if (!$field) {
            return null;
        }
        // Fetch the custom field value for this service
        $valueRow = Capsule::table('tblcustomfieldsvalues')
            ->where('fieldid', $field->id)
            ->where('relid', $serviceId)
            ->first();
        return $valueRow ? $valueRow->value : null;
    }

    /**
     * Save a value to a custom field (create the field if it does not exist for the product).
     */
    public static function saveCustomFieldValue($params, $fieldName, $value)
    {
        $serviceId = $params['serviceid'];
        $productId = $params['pid'];
        // Ensure the custom field exists for this product
        $field = Capsule::table('tblcustomfields')
            ->where('type', 'product')
            ->where('relid', $productId)
            ->where('fieldname', $fieldName)
            ->first();
        if (!$field) {
            // Create the custom field for this product if it doesn't exist
            $fieldId = Capsule::table('tblcustomfields')->insertGetId([
                'type'        => 'product',
                'relid'       => $productId,
                'fieldname'   => $fieldName,
                'fieldtype'   => 'text',
                'description' => '',      // no description
                'fieldoptions'=> '',      // no options (text field)
                'required'    => 0,
                'showorder'   => 0,
                'showinvoice' => 0,
                'adminonly'   => 1,       // admin only (hidden from client)
            ]);
        } else {
            $fieldId = $field->id;
        }
        // Insert or update the custom field value for this service
        $existingVal = Capsule::table('tblcustomfieldsvalues')
            ->where('fieldid', $fieldId)
            ->where('relid', $serviceId)
            ->first();
        if ($existingVal) {
            // Update existing value
            Capsule::table('tblcustomfieldsvalues')
                ->where('id', $existingVal->id)
                ->update(['value' => $value]);
        } else {
            // Insert new value
            Capsule::table('tblcustomfieldsvalues')->insert([
                'fieldid' => $fieldId,
                'relid'   => $serviceId,
                'value'   => $value
            ]);
        }
    }
}
