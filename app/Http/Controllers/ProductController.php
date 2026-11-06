<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Services\ProductImporter;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $category = $request->query('category');

        $products = Product::with('carton')
            ->when($q !== '', fn ($query) => $query->where(function ($w) use ($q) {
                $w->where('part_number', 'like', "%{$q}%")->orWhere('name', 'like', "%{$q}%");
            }))
            ->when(in_array($category, ['small', 'heavy'], true), fn ($query) => $query->where('category', $category))
            ->orderBy('part_number')
            ->paginate(25)
            ->withQueryString();

        return view('products.index', [
            'products' => $products,
            'total' => Product::count(),
            'q' => $q,
            'category' => $category,
        ]);
    }

    public function import(Request $request, ProductImporter $importer)
    {
        $request->validate([
            'product_file' => 'required|file|mimes:xlsx,xls,csv,txt',
        ], [
            'product_file.required' => 'Pilih file master barang dulu.',
            'product_file.mimes' => 'Format file harus .xlsx, .xls, atau .csv.',
        ]);

        $result = $importer->import($request->file('product_file'));

        return redirect()->route('products.index')
            ->with('status', "Impor selesai: {$result['created']} barang baru, {$result['updated']} diperbarui, {$result['skipped']} dilewati.")
            ->with('import_messages', $result['messages']);
    }
}
