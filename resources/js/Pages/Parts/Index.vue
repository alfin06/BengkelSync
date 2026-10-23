<script setup lang="ts">
import { ref, nextTick, watch, computed } from 'vue';
import { useForm, Head, router, Link, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { 
    Dialog, 
    DialogContent, 
    DialogHeader, 
    DialogTitle, 
    DialogFooter 
} from '@/Components/ui/dialog';
import { Package, Wrench, Plus, AlertTriangle, Search, Edit2, Tag, Banknote, X, Trash2, Save } from 'lucide-vue-next';
import debounce from 'lodash/debounce';

const props = defineProps<{ 
    parts: {
        data: any[];
        links: any[];
    };
    filters: {
        search: string;
    };
}>();

const page = usePage();
const user = computed(() => page.props.auth?.user);

const showAddForm = ref(false);
const showEditModal = ref(false);
const selectedPartId = ref<number | null>(null);
const search = ref(props.filters?.search || '');

// --- Search Logic ---
watch(search, debounce((value) => {
    router.get(route('parts.index'), 
        { search: value }, 
        { 
            preserveState: true, 
            preserveScroll: true,
            replace: true,
            only: ['parts', 'filters'] // Only fetch these specific props
        }
    );
}, 300));

// --- Forms ---
const form = useForm({
    name: '',
    sku: '',
    buy_price: 0,
    sell_price: 0,
    stock: 0,
    type: 'Part',
});

const editForm = useForm({
    name: '',
    sku: '',
    buy_price: 0,
    sell_price: 0,
    discount_percent: 0, 
    stock: 0,
    min_stock: 5,
    type: 'Part',
});

// --- Actions ---
const openEditModal = (part: any) => {
    selectedPartId.value = part.id;
    editForm.name = part.name;
    editForm.sku = part.sku || '';
    editForm.buy_price = part.buy_price || 0;
    editForm.sell_price = part.sell_price || 0;
    editForm.discount_percent = part.discount_percent ?? 0; 
    editForm.stock = part.stock || 0;
    editForm.min_stock = part.min_stock ?? 5;
    editForm.type = part.type;
    editForm.clearErrors();
    showEditModal.value = true;
};

const submitAdd = () => {
    form.post(route('parts.store'), {
        onSuccess: () => {
            form.reset();
            showAddForm.value = false;
        },
    });
};

const submitUpdate = () => {
    editForm.put(route('parts.update', selectedPartId.value), {
        preserveScroll: true,
        onSuccess: () => {
            showEditModal.value = false;
            editForm.reset();
        },
    });
};

// --- Utilities ---
watch(showAddForm, async (newValue) => {
    if (newValue) {
        await nextTick();
        document.getElementById('sku-field')?.focus();
    }
});

const handleSkuEnter = () => {
    document.getElementById('name-field')?.focus();
};

const formatIDR = (value: number) => {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0
    }).format(value);
};

const calculateFinalPrice = (price: any, discount: any) => {
    const p = parseFloat(price) || 0;
    const d = parseFloat(discount) || 0;
    if (d <= 0) return p;
    return p * (1 - (d / 100));
};

const deletePart = () => {
    if (confirm('Hapus barang ini? Data tidak akan hilang dari riwayat servis lama, tapi tidak akan muncul lagi di stok.')) {
        router.delete(route('parts.destroy', selectedPartId.value), {
            onSuccess: () => {
                showEditModal.value = false;
            },
        });
    }
};
</script>

<template>
    <Head title="Inventory Sparepart" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="text-xl font-semibold text-gray-800">Inventory & Jasa</h2>
                <div class="flex gap-2">
                    <Link :href="route('stock-entries.create')">
                        <Button variant="outline" class="border-blue-200 text-blue-600 hover:bg-blue-50">
                            <Package class="w-4 h-4 mr-2" /> Stok Masuk
                        </Button>
                    </Link>
                    <Button @click="showAddForm = !showAddForm" :class="showAddForm ? 'bg-slate-500' : 'bg-blue-600'">
                        <component :is="showAddForm ? X : Plus" class="w-4 h-4 mr-2" /> 
                        {{ showAddForm ? 'Batal' : 'Tambah Item' }}
                    </Button>
                </div>
            </div>
        </template>

        <div class="py-12 max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            
            <Card v-show="showAddForm" class="border-blue-200 bg-blue-50/40 shadow-sm">
                <CardHeader class="pb-2 border-b border-blue-100 mb-4">
                    <CardTitle class="text-blue-700 font-bold uppercase tracking-widest text-xs flex items-center gap-2">
                        <Plus class="w-4 h-4" /> Entry Data Barang Baru
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <form @submit.prevent="submitAdd" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div class="md:col-span-1">
                            <Label class="text-[10px] font-bold uppercase text-slate-500 tracking-wider">SKU / Barcode</Label>
                            <Input id="sku-field" v-model="form.sku" placeholder="Scan..." @keydown.enter.prevent="handleSkuEnter" />
                        </div>
                        <div class="md:col-span-2">
                            <Label class="text-[10px] font-bold uppercase text-slate-500 tracking-wider">Nama Barang / Jasa</Label>
                            <Input id="name-field" v-model="form.name" placeholder="Contoh: Oli Mesin" required />
                        </div>
                        <div class="md:col-span-1">
                            <Label class="text-[10px] font-bold uppercase text-slate-500 tracking-wider">Tipe</Label>
                            <select v-model="form.type" class="w-full border border-input rounded-md px-3 py-2 h-10 text-sm bg-white">
                                <option value="Part">Part</option>
                                <option value="Service">Jasa</option>
                            </select>
                        </div>
                        <div>
                            <Label class="text-[10px] font-bold uppercase text-slate-500 tracking-wider">Harga Modal</Label>
                            <Input v-model="form.buy_price" type="number" />
                        </div>
                        <div>
                            <Label class="text-[10px] font-bold uppercase text-slate-500 tracking-wider">Harga Jual</Label>
                            <Input v-model="form.sell_price" type="number" />
                        </div>
                        <div>
                            <Label class="text-[10px] font-bold uppercase text-slate-500 tracking-wider">Stok Awal</Label>
                            <Input v-model="form.stock" type="number" :disabled="form.type === 'Service'" />
                        </div>
                        <div class="flex items-end">
                            <Button class="w-full bg-green-600 hover:bg-green-700 h-10 font-bold uppercase text-xs tracking-widest" :disabled="form.processing">
                                {{ form.processing ? 'Proses...' : 'Simpan Item' }}
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>

            <div class="flex items-center max-w-md relative">
                <Search class="absolute left-3 w-4 h-4 text-slate-400" />
                <Input v-model="search" placeholder="Cari nama atau scan barcode..." class="pl-10 h-11 bg-white shadow-sm" />
            </div>

            <Card class="overflow-hidden shadow-sm border-slate-200">
                <CardContent class="p-0">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-slate-50 border-b text-[10px] uppercase text-slate-500 font-black tracking-widest">
                                    <th class="p-4">Item & SKU</th>
                                    <th class="p-4">Tipe</th>
                                    <th class="p-4 text-center">Stok</th>
                                    <th class="p-4 text-right">Harga Jual</th>
                                    <th v-if="['admin', 'finance'].includes($page.props.auth.user.role)" class="p-4 text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-for="part in parts.data" :key="part.id" class="hover:bg-blue-50/20 transition-colors">
                                    <td class="p-4">
                                        <div class="font-bold text-slate-900">
                                            <Link :href="route('parts.show', part.id)" class="font-bold text-blue-600 hover:underline">
                                                {{ part.name }}
                                            </Link>
                                        </div>
                                        <div class="text-[11px] text-slate-400 font-mono">{{ part.sku || 'N/A' }}</div>
                                    </td>
                                    <td class="p-4">
                                        <span :class="['px-2 py-0.5 rounded text-[10px] font-bold uppercase', 
                                            part.type === 'Part' ? 'bg-blue-50 text-blue-700 border border-blue-100' : 'bg-orange-50 text-orange-700 border border-orange-100']">
                                            {{ part.type }}
                                        </span>
                                    </td>
                                    <td class="p-4 text-center">
                                        <div v-if="part.type === 'Part'" :class="['text-sm', part.stock <= (part.min_stock || 5) ? 'text-red-600 font-black italic' : 'text-slate-700 font-medium']">
                                            {{ part.stock }}
                                            <AlertTriangle v-if="part.stock <= (part.min_stock || 5)" class="inline w-3 h-3 ml-1 animate-pulse" />
                                        </div>
                                        <div v-else class="text-slate-300 text-[11px] italic font-medium uppercase tracking-tighter">Unlimited</div>
                                    </td>
                                    <td class="p-4 text-right font-mono">
                                        <div v-if="part.discount_percent > 0">
                                            <span class="text-[10px] text-slate-400 line-through mr-2">{{ formatIDR(part.sell_price) }}</span>
                                            <span class="font-bold text-green-600">{{ formatIDR(calculateFinalPrice(part.sell_price, part.discount_percent)) }}</span>
                                        </div>
                                        <span v-else class="font-bold text-blue-700">{{ formatIDR(part.sell_price) }}</span>
                                    </td>
                                    <td v-if="['admin', 'finance'].includes($page.props.auth.user.role)" class="p-4 text-center">
                                        <Button variant="ghost" size="icon" @click="openEditModal(part)" class="text-slate-400 hover:text-blue-600 h-8 w-8">
                                            <Edit2 class="w-4 h-4" />
                                        </Button>
                                    </td>
                                </tr>
                                <tr v-if="parts.data.length === 0">
                                    <td colspan="5" class="p-12 text-center text-slate-400 italic">Belum ada data barang.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div v-if="parts.links.length > 3" class="p-4 border-t flex justify-center items-center gap-1 bg-slate-50">
                        <template v-for="(link, k) in parts.links" :key="k">
                            <div v-if="link.url === null" class="px-3 py-1.5 text-xs text-slate-400 border rounded bg-white opacity-50" v-html="link.label" />
                            <Link v-else :href="link.url" v-html="link.label" class="px-3 py-1.5 text-xs border rounded transition-all"
                                 :class="{ 'bg-blue-600 text-white border-blue-600 shadow-sm': link.active, 'bg-white text-slate-700 hover:bg-slate-50': !link.active }" 
                                 preserve-scroll
                            />
                        </template>
                    </div>
                </CardContent>
            </Card>
        </div>

        <Dialog v-if="['admin', 'finance'].includes($page.props.auth.user.role)" :open="showEditModal" @update:open="showEditModal = $event">
            <DialogContent class="sm:max-w-[550px]">
                <DialogHeader><DialogTitle class="flex items-center gap-2 font-bold"><Edit2 class="w-5 h-5 text-blue-600"/> Edit Data Item</DialogTitle></DialogHeader>
                <form @submit.prevent="submitUpdate" class="space-y-4 py-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div class="col-span-2">
                            <Label class="text-xs font-bold uppercase text-slate-500">Nama Barang / Jasa</Label>
                            <Input v-model="editForm.name" required />
                        </div>
                        <div class="col-span-1">
                            <Label class="text-xs font-bold uppercase text-slate-500">SKU</Label>
                            <Input v-model="editForm.sku" />
                        </div>
                        <div class="col-span-1">
                            <Label class="text-xs font-bold uppercase text-slate-500">Tipe</Label>
                            <select v-model="editForm.type" class="w-full border border-input rounded-md px-3 py-2 text-sm bg-white">
                                <option value="Part">Sparepart</option>
                                <option value="Service">Jasa</option>
                            </select>
                        </div>
                        <div class="col-span-1">
                            <Label class="text-xs font-bold uppercase text-slate-500 flex items-center gap-1"><Banknote class="w-3 h-3 text-red-400" /> Modal</Label>
                            <Input v-model="editForm.buy_price" type="number" />
                        </div>
                        <div class="col-span-1">
                            <Label class="text-xs font-bold uppercase text-slate-500">Retail</Label>
                            <Input v-model="editForm.sell_price" type="number" />
                        </div>
                        <div class="col-span-1">
                            <Label class="text-xs font-bold uppercase text-slate-500 flex items-center gap-1"><Tag class="w-3 h-3 text-blue-400" /> Diskon (%)</Label>
                            <Input v-model="editForm.discount_percent" type="number" min="0" max="100" />
                        </div>
                        <div class="col-span-1">
                            <Label class="text-xs font-bold uppercase text-slate-500">Stok</Label>
                            <Input v-model="editForm.stock" type="number" :disabled="editForm.type === 'Service'" />
                        </div>
                        <div class="col-span-1">
                            <Label class="text-xs font-bold uppercase text-slate-500">Min. Stok</Label>
                            <Input v-model="editForm.min_stock" type="number" />
                        </div>
                        <div class="col-span-1 bg-blue-50 p-3 rounded-lg border border-blue-100 flex flex-col justify-center items-center">
                            <p class="text-[10px] font-bold text-blue-400 uppercase tracking-tighter">Harga Net</p>
                            <p class="text-md font-black text-blue-700">{{ formatIDR(calculateFinalPrice(editForm.sell_price, editForm.discount_percent)) }}</p>
                        </div>
                    </div>
                    <DialogFooter class="pt-4 border-t flex justify-between items-center w-full">
                        <Button 
                            type="button" 
                            variant="ghost" 
                            class="text-red-500 hover:text-red-700 hover:bg-red-50"
                            @click="deletePart">
                            <Trash2 class="w-4 h-4 mr-2" /> Hapus
                        </Button>

                        <div class="flex gap-2">
                            <Button type="button" variant="outline" @click="showEditModal = false">Batal</Button>
                            <Button type="submit" class="bg-blue-600 px-8 font-bold uppercase text-xs" :disabled="editForm.processing">
                                <Save class="w-4 h-4 mr-2" /> Simpan
                            </Button>
                        </div>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </AuthenticatedLayout>
</template>