/** App\Http\Resources\MechanicResource */
export interface Mechanic {
    id: number;
    name: string;
    /** Display format, e.g. 0812-3456-7890 */
    phone: string | null;
    phone_e164: string | null;
    is_active: boolean;
    notes: string | null;
    /** Full NIK, only for users with mechanic.view_identity */
    nik: string | null;
    /** e.g. "3374••••••••0001" */
    nik_masked: string | null;
    has_ktp: boolean;
    /** Only for users with mechanic.view_identity */
    ktp_url: string | null;
}

export interface MechanicAbilities {
    create: boolean;
    update: boolean;
    delete: boolean;
    identity: boolean;
}
