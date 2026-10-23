import type { SelectOption } from '@/types/ui';

/** App\Http\Resources\VehicleResource */
export interface Vehicle {
    id: number;
    customer_id: number;
    plate_number: string;
    name: string;
    make: string;
    model: string;
    year: number | null;
    color: string | null;
    vin: string | null;
    engine_number: string | null;
    transmission: string | null;
    transmission_label: string | null;
    fuel_type: string | null;
    fuel_type_label: string | null;
    last_odometer: number | null;
    notes: string | null;
    customer?: { id: number; name: string };
}

/** App\Http\Resources\CustomerResource */
export interface Customer {
    id: number;
    type: 'individual' | 'company';
    type_label: string;
    name: string;
    /** Display format, e.g. 0812-3456-7890 */
    phone: string | null;
    phone_e164: string | null;
    whatsapp: string | null;
    whatsapp_link: string | null;
    email: string | null;
    address: string | null;
    npwp: string | null;
    notes: string | null;
    vehicles?: Vehicle[];
}

export interface VehicleOwnership {
    id: number;
    customer: { id: number; name: string };
    started_on: string;
    ended_on: string | null;
    reason: string | null;
}

export interface VehicleFormOptions {
    suggestions: { makes: string[]; models: Record<string, string[]> };
    transmissions: SelectOption[];
    fuelTypes: SelectOption[];
}
