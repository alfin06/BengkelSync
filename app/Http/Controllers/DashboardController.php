<?php

namespace App\Http\Controllers;

use App\Models\WorkOrder;
use App\Models\Invoice;
use App\Models\Expense;
use App\Models\Mechanic;
use App\Models\Part;
use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Http\Request;

use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        // 1. The "Boss View" Redirect
        if ($user && $user->isAdmin()) {
            return redirect()->route('admin.dashboard');
        }

        // 2. Fetch Data for Staff & Finance
        $activeWorkOrders = WorkOrder::with(['vehicle.customer'])
            ->where('status', '!=', 'Selesai')
            ->orderBy('created_at', 'desc')
            ->get();

        $receivablesDueToday = Invoice::with(['workOrder.vehicle.customer'])
            ->where('status', 'unpaid')
            ->whereDate('due_date', '<=', Carbon::today())
            ->get();

        $remindersDue = Vehicle::needsReminder()->take(10)->get()->map(function($vehicle) {
            // Construct the custom WhatsApp message dynamically
            $phoneNumber = preg_replace('/\D+/', '', (string) $vehicle->customer->phone_1);
            $message = "Halo Bpk./Ibu " . $vehicle->customer->name . ",\n\n" .
                    "Mobil " . $vehicle->brand . " " . $vehicle->model . " (" . $vehicle->plate_number . ") " .
                    "sudah 3 bulan sejak servis terakhir di Bengkel Kalimas Lumas. " .
                    "Yuk, ganti oli berkala agar mesin tetap prima! Silakan balas pesan ini untuk booking slot minggu ini. Terima kasih! 🙏";

            return [
                'id' => $vehicle->id,
                'plate_number' => $vehicle->plate_number,
                'customer_name' => $vehicle->customer->name,
                'phone' => $vehicle->customer->phone_1,
                'vehicle_info' => $vehicle->brand . ' ' . $vehicle->model,
                'last_serviced' => $vehicle->last_serviced_at,
                'wa_url' => "https://api.whatsapp.com/send?phone=" . $phoneNumber . "&text=" . urlencode($message)
            ];
        });

        return inertia('Dashboard/Staff', [
            'activeWorkOrders' => $activeWorkOrders,
            'receivablesDueToday' => $receivablesDueToday,
            'remindersDue' => $remindersDue,
        ]);
    }
}