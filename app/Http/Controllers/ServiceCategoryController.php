<?php

namespace App\Http\Controllers;

use App\Models\ServiceCategory;
use Illuminate\Http\RedirectResponse;

class ServiceCategoryController extends Controller
{
    public function show(ServiceCategory $serviceCategory): RedirectResponse
    {
        abort_unless($serviceCategory->is_active, 404);

        return redirect()->route('workers.index', ['category' => $serviceCategory->slug]);
    }
}
