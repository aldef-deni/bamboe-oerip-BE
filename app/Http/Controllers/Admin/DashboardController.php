<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\User;
use App\Models\Villa;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $revenue = Booking::whereIn('status', ['confirmed', 'completed'])->sum('total');

        $bookingsByStatus = Booking::select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $recentBookings = Booking::with(['villa:id,name', 'user:id,name'])
            ->latest()
            ->limit(8)
            ->get();

        // Revenue for last 6 months
        $monthly = Booking::whereIn('status', ['confirmed', 'completed'])
            ->where('created_at', '>=', now()->subMonths(6)->startOfMonth())
            ->select(
                DB::raw("DATE_FORMAT(created_at, '%Y-%m') as month"),
                DB::raw('SUM(total) as revenue'),
                DB::raw('COUNT(*) as bookings')
            )
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        return response()->json([
            'stats' => [
                'villas' => Villa::count(),
                'active_villas' => Villa::where('is_active', true)->count(),
                'users' => User::where('role', 'user')->count(),
                'bookings' => Booking::count(),
                'pending_verification' => Payment::where('status', 'submitted')->count(),
                'pending_villa_review' => Villa::where('review_status', 'pending')->count(),
                'owners' => User::where('role', 'owner')->count(),
                'revenue' => (float) $revenue,
            ],
            'bookings_by_status' => $bookingsByStatus,
            'recent_bookings' => $recentBookings,
            'monthly' => $monthly,
        ]);
    }
}
