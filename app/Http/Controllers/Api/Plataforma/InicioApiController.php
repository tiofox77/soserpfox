<?php

namespace App\Http\Controllers\Api\Plataforma;

use App\Http\Controllers\Controller;
use App\Services\Plataforma\InicioDaPlataforma;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** A página inicial do super admin — ver App\Services\Plataforma\InicioDaPlataforma. */
class InicioApiController extends Controller
{
    public function index(Request $request, InicioDaPlataforma $inicio): JsonResponse
    {
        return response()->json($inicio->dados($request->user()));
    }
}