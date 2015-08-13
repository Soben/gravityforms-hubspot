<?php

class GF_Hubspot_Migration {

    private static $_v2_table_name;
    private static $_v2_formfield_base;

    public function migrate_to_v2 () {
        GF_Hubspot_Tracking::log('Migration Assistance is running');

        global $wpdb;

        self::$_v2_table_name = $wpdb->prefix . "rg_hubspot_connections";
        self::$_v2_formfield_base = 'hsfield_';

        // Get the plugin instance
        $gf_hubspot = gf_hubspot();

        // Migrate the Settings
        $gf_hubspot->bsd_set('hub_id', get_option('gf_bsdhubspot_portal_id'));
        $gf_hubspot->bsd_set('connection_type', get_option('gf_bsdhubspot_connection_type'));
        $gf_hubspot->bsd_set('token_oauth', get_option('gf_bsdhubspot_oauth_token'));
        $gf_hubspot->bsd_set('token_apikey', get_option('gf_bsdhubspot_api_key'));
        $gf_hubspot->bsd_set('include_js', (get_option("gf_bsdhubspot_include_analytics") == "yes" ? 1 : 0));

        // Foreach existing connection, migrate over.
        $connections = self::pre_v2_connections();
        $count = 1;
        if ( $connections ) : 
            GF_Hubspot_Tracking::log('Migrating found connections.');
            foreach ( $connections as $connection ) :
            $name = 'Migrated Feed ' . $count;
            $gform_id = $connection->gravityforms_id;

            $meta = array (
                'feedName'  => $name,
                'formID'    => $connection->hubspot_id,
            );

            foreach ( $connection->form_data['connections'] as $hs_field_name => $data ) {
                $slug = 'fieldMap_' . $hs_field_name;
                $meta[$slug] = $data['gf_field_name'];
            }

            $gf_hubspot->insert_feed($gform_id, $is_active=1, $meta);

            $count++;
        endforeach; endif;

        // After this runs successfully
        // Add admin success notification.
        add_action( 'admin_notices', function(){
            echo '
                <div class="updated">
                    <p>HubSpot for Gravity Forms has been successfully updated and migrated. Please verify your settings and connections before continuing.</p>
                    <p>Your "Connections" have been moved to each indivudual form and can be found under Settings > HubSpot for each form respectively.</p>
                </div>
            ';
        });
        delete_transient('gf_hubspot_needs_migration');

        // Delete all of the older stuff.
        delete_option('gf_bsdhubspot_portal_id');
        delete_option('gf_bsdhubspot_connection_type');
        delete_option('gf_bsdhubspot_oauth_token');
        delete_option('gf_bsdhubspot_api_key');
        delete_option("gf_bsdhubspot_include_analytics");
        
        // Drop the table.
        $sql = "DROP TABLE IF EXISTS ".self::$_v2_table_name."";
        $wpdb->query( $sql );
    } // function

    public function pre_v2_connections () {
        global $wpdb;

        $sql = "SELECT * FROM ".self::$_v2_table_name."";
        $sql .= " ORDER BY `id` desc";

        $results = $wpdb->get_results($sql);

        if ( !is_array($results) || count($results) == 0 ) {
            return FALSE;
        }

        $output = array ();
        foreach ( $results as $result ) {
            $result->form_data = unserialize($result->form_data);
            $output[] = $result;
        }

        return $output;
    } // function

} // class