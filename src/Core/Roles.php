<?php

namespace WpOrg\Core;

class Roles
{
    public function register()
    {
        add_role('org_member', 'Org Member', [
            'read' => true,
        ]);

        add_role('org_admin', 'Org Admin', [
            'read' => true,
            'list_users' => true,
            'edit_users' => true,
            'promote_users' => true,
            'create_users' => true,
            'delete_users' => false,
            'wp_org_manage_members' => true,
            'wp_org_approve_members' => true,
            'wp_org_manage_settings' => true,
        ]);

        $admin = get_role('administrator');
        if ($admin) {
            $admin->add_cap('wp_org_manage_members');
            $admin->add_cap('wp_org_approve_members');
            $admin->add_cap('wp_org_manage_settings');
        }
    }
}
