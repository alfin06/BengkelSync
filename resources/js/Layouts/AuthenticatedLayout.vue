<script setup>
import { ref, computed, watch } from 'vue';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import NavLink from '@/Components/NavLink.vue';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink.vue';
import { Link, usePage } from '@inertiajs/vue3';
import { Toaster, toast } from 'vue-sonner';

const showingNavigationDropdown = ref(false);

const page = usePage();

// Safely fetch user data
const user = computed(() => page.props.auth?.user ?? null);

const cleanLogoUrl = computed(() => {
    // 1. If the database has a path, use it (ensuring it starts with /storage/)
    const dbPath = page.props.shop?.logo_path;
    
    if (dbPath) {
        // If the DB says "logo/logo.png", we turn it into "/storage/logo/logo.png"
        return dbPath.startsWith('http') ? dbPath : `/storage/${dbPath.replace(/^\//, '')}`;
    }
    
    // 2. Hardcoded fallback to your actual file location
    return '/storage/logo/logo.png'; 
});

// Global listener for Flash Messages
watch(() => page.props.flash, (flash) => {
    if (flash?.success) {
        toast.success(flash.success);
    }
    if (flash?.error) {
        toast.error(flash.error);
    }
}, { deep: true });
</script>

<template>
    <Toaster 
        class="no-print"
        position="top-right" 
        expand 
        richColors 
        closeButton
        :toastOptions="{
            style: { 
                position: 'fixed',
                top: '20px',
                right: '20px',
                zIndex: 99999,
                background: '#ffffff',
                border: '2px solid #e2e8f0',
                padding: '16px',
                borderRadius: '12px',
                boxShadow: '0 20px 25px -5px rgba(0, 0, 0, 0.2)',
            },
        }" 
    />
        
    <div>
        <div class="min-h-screen bg-gray-100">
            <nav class="no-print border-b border-gray-200 bg-white shadow-sm">
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div class="flex h-16 justify-between">
                        
                        <div class="flex">
                            <div class="flex shrink-0 items-center">
                                <Link :href="user?.role === 'admin' ? route('admin.dashboard') : route('dashboard')">
                                    <ApplicationLogo 
                                        :logoUrl="cleanLogoUrl"
                                        class="block h-16 w-auto fill-current text-blue-600" 
                                    />
                                </Link>
                            </div>

                            <div class="hidden space-x-6 sm:-my-px sm:ms-10 sm:flex">
                                
                                <template v-if="user?.role === 'admin'">
                                    <NavLink  :href="route('admin.dashboard')" :active="route().current('admin.dashboard')">
                                        Dashboard
                                    </NavLink>
                                </template>

                                <template v-if="['staff', 'finance'].includes(user?.role)">
                                    <NavLink :href="route('dashboard')" :active="route().current('dashboard')">
                                        Dashboard
                                    </NavLink>
                                </template>

                                <template v-if="user?.role === 'admin' || user?.role === 'finance'">
                                    <NavLink  :href="route('admin.work-orders.monitor')" :active="route().current('admin.work-orders.monitor')">
                                        Monitoring
                                    </NavLink>
                                </template>

                                <NavLink :href="route('work-orders.quick-checkin')" :active="route().current('work-orders.quick-checkin')">
                                    <div class="flex items-center gap-2">
                                        Check-in
                                    </div>
                                </NavLink>
                                <NavLink :href="route('customers.index')" :active="route().current('customers.index')">
                                    Pelanggan
                                </NavLink>
                                <NavLink :href="route('expenses.index')" :active="route().current('expenses.index')">
                                    Buku Pengeluaran
                                </NavLink>
                                
                                <div class="inline-flex items-center">
                                    <Dropdown align="left" width="48">
                                        <template #trigger>
                                            <button type="button" 
                                                class="inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium leading-5 transition duration-150 ease-in-out h-16 focus:outline-none"
                                                :class="route().current('parts.*') || route().current('stock-entries.*') || route().current('suppliers.*') 
                                                    ? 'border-blue-600 text-gray-900' 
                                                    : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'">
                                                Stok
                                                <svg class="ms-1 h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                                </svg>
                                            </button>
                                        </template>

                                        <template #content>
                                            <DropdownLink :href="route('parts.index')"> Data Stok </DropdownLink>
                                            <DropdownLink :href="route('suppliers.index')">
                                                Data Supplier
                                            </DropdownLink>
                                        </template>
                                    </Dropdown>
                                </div>

                                <div class="inline-flex items-center">
                                    <Dropdown align="left" width="48">
                                        <template #trigger>
                                            <button type="button" 
                                                class="inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium leading-5 transition duration-150 ease-in-out h-16 focus:outline-none"
                                                :class="route().current('mechanics.*') ? 'border-blue-600 text-gray-900' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'">
                                                Data Master
                                                <svg class="ms-1 h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                                </svg>
                                            </button>
                                        </template>

                                        <template #content>
                                            <DropdownLink :href="route('mechanics.index')">
                                                Data Mekanik
                                            </DropdownLink>
                                        </template>
                                    </Dropdown>
                                </div>
                            </div>
                        </div>

                        <div class="hidden sm:flex sm:items-center sm:ms-6">
                            <div class="relative ms-3">
                                <Dropdown align="right" width="48">
                                    <template #trigger>
                                        <span class="inline-flex rounded-md">
                                            <button type="button" class="inline-flex items-center rounded-md border border-transparent bg-white px-3 py-2 text-sm font-medium leading-4 text-gray-600 transition duration-150 ease-in-out hover:text-gray-900 focus:outline-none">
                                                <span v-if="user?.role" class="mr-2 px-2 py-0.5 text-[10px] uppercase font-bold tracking-wider rounded-full" 
                                                      :class="user.role === 'admin' ? 'bg-blue-100 text-blue-700' : 'bg-slate-100 text-slate-600'">
                                                    {{ user.role }}
                                                </span>
                                                {{ user?.name || 'User' }}
                                                <svg class="-me-0.5 ms-2 h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                                </svg>
                                            </button>
                                        </span>
                                    </template>

                                    <template #content>
                                        <DropdownLink v-if="user?.role === 'admin'" :href="route('admin.users.index')">
                                            Pengaturan User
                                        </DropdownLink>
                                        <DropdownLink v-if="user?.role === 'admin'" :href="route('admin.settings.edit')">
                                            Pengaturan Bengkel
                                        </DropdownLink>
                                        <DropdownLink v-if="user?.role === 'admin'" :href="route('admin.reports.index')">
                                            Laporan Keuangan
                                        </DropdownLink>
                                        <DropdownLink :href="route('profile.edit')"> Profile </DropdownLink>
                                        <DropdownLink :href="route('logout')" method="post" as="button"> Log Out </DropdownLink>
                                    </template>
                                </Dropdown>
                            </div>
                        </div>

                        <div class="-me-2 flex items-center sm:hidden">
                            <button @click="showingNavigationDropdown = !showingNavigationDropdown" class="inline-flex items-center justify-center rounded-md p-2 text-gray-400 transition duration-150 ease-in-out hover:bg-gray-100 hover:text-gray-500 focus:bg-gray-100 focus:text-gray-500 focus:outline-none">
                                <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                                    <path :class="{ hidden: showingNavigationDropdown, 'inline-flex': !showingNavigationDropdown }" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                                    <path :class="{ hidden: !showingNavigationDropdown, 'inline-flex': showingNavigationDropdown }" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                <div :class="{ block: showingNavigationDropdown, hidden: !showingNavigationDropdown }" class="sm:hidden no-print">
                    <div class="space-y-1 pb-3 pt-2">
                        
                        <template v-if="user?.role === 'admin'">
                            <ResponsiveNavLink :href="route('admin.dashboard')" :active="route().current('admin.dashboard')"> Dashboard </ResponsiveNavLink>
                            <ResponsiveNavLink :href="route('admin.work-orders.monitor')" :active="route().current('admin.work-orders.monitor')"> 
                                Monitoring 
                            </ResponsiveNavLink>
                        </template>

                        <template v-if="['staff', 'finance'].includes(user?.role)">
                            <ResponsiveNavLink :href="route('dashboard')" :active="route().current('dashboard')"> Dashboard </ResponsiveNavLink>
                        </template>

                        <ResponsiveNavLink :href="route('work-orders.quick-checkin')" :active="route().current('work-orders.quick-checkin')"> Check-In</ResponsiveNavLink>
                        <ResponsiveNavLink :href="route('customers.index')" :active="route().current('customers.index')"> Pelanggan </ResponsiveNavLink>
                        <ResponsiveNavLink :href="route('expenses.index')" :active="route().current('expenses.index')"> Buku Pengeluaran </ResponsiveNavLink>
                        
                        <div class="pt-4 pb-1 border-t border-gray-100">
                            <div class="px-4 text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">Manajemen Stok</div>
                            <ResponsiveNavLink :href="route('parts.index')" :active="route().current('parts.index')" class="pl-8"> Data Stok </ResponsiveNavLink>
                            <!-- <ResponsiveNavLink :href="route('parts.restock.page')" :active="route().current('parts.restock.page')" class="pl-8"> Restock Barang </ResponsiveNavLink> -->
                            <ResponsiveNavLink :href="route('mechanics.index')" :active="route().current('mechanics.*')"> Data Mekanik </ResponsiveNavLink>
                        </div>
                    </div>

                    <div class="border-t border-gray-200 pb-1 pt-4">
                        <div class="px-4 flex items-center gap-2">
                            <div>
                                <div class="text-base font-medium text-gray-800">{{ user?.name }}</div>
                                <div class="text-sm font-medium text-gray-500">{{ user?.email }}</div>
                            </div>
                        </div>

                        <div class="mt-3 space-y-1">
                            <ResponsiveNavLink v-if="user?.role === 'admin'" :href="route('admin.settings.edit')" :active="route().current('admin.settings.edit')">
                                Pengaturan Bengkel
                            </ResponsiveNavLink>
                            <ResponsiveNavLink :href="route('profile.edit')"> Profile </ResponsiveNavLink>
                            <ResponsiveNavLink :href="route('logout')" method="post" as="button"> Log Out </ResponsiveNavLink>
                        </div>
                    </div>
                </div>
            </nav>

            <header class="no-print bg-white shadow-sm" v-if="$slots.header">
                <div class="mx-auto max-w-7xl px-4 py-4 sm:px-6 lg:px-8">
                    <slot name="header" />
                </div>
            </header>

            <main>
                <slot />
            </main>
        </div>
    </div>
</template>

<style>
@media print {
    .no-print {
        display: none !important;
    }
    
    body {
        background-color: white !important;
    }

    .min-h-screen {
        background-color: white !important;
        min-height: auto !important;
    }
}
</style>