<?php

namespace App\Containers\AppSection\Authorization\Enums;

enum Role: string
{
    case SUPER_ADMIN         = 'admin';
    case ASSISTANT           = 'assistant';
    case LEAD_PRINT          = 'lead_print';
    case LEAD_PICK           = 'lead_pick';
    case LEAD_CUT_MOCKUP     = 'lead_cut_mockup';
    case LEAD_PACK_SHIP      = 'lead_pack_ship';
    case DASHBOARD_PRINT_DTF = 'dashboard_print_dtf';
    case DASHBOARD_PRINT_DTG = 'dashboard_print_dtg';
    case DASHBOARD_PICK      = 'dashboard_pick';
    case DASHBOARD_CUT       = 'dashboard_cut';
    case DASHBOARD_MOCKUP    = 'dashboard_mockup';
    case DASHBOARD_PACK_SHIP = 'dashboard_pack_ship';

    public function label(): string
    {
        return match ($this) {
            self::SUPER_ADMIN         => 'Super Admin',
            self::ASSISTANT           => 'Assistant',
            self::LEAD_PRINT          => 'Lead Print',
            self::LEAD_PICK           => 'Lead Pick',
            self::LEAD_CUT_MOCKUP     => 'Lead Cut + Mockup',
            self::LEAD_PACK_SHIP      => 'Lead Pack & Ship',
            self::DASHBOARD_PRINT_DTF => 'Dashboard Print DTF',
            self::DASHBOARD_PRINT_DTG => 'Dashboard Print DTG',
            self::DASHBOARD_PICK      => 'Dashboard Pick',
            self::DASHBOARD_CUT       => 'Dashboard Cut',
            self::DASHBOARD_MOCKUP    => 'Dashboard Mockup',
            self::DASHBOARD_PACK_SHIP => 'Dashboard Pack & Ship',
        };
    }
}
