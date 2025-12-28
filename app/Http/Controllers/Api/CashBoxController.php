<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CashBoxService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CashBoxController extends Controller
{
    protected CashBoxService $cashBoxService;

    public function __construct(CashBoxService $cashBoxService)
    {
        $this->cashBoxService = $cashBoxService;
    }

    /**
     * Get cash box summary (current open box)
     * Permission: view_cash_box
     */
    public function getCashBoxSummary(Request $request): JsonResponse
    {
        if (!$request->user()->hasPermission('view_cash_box')) {
            return response()->json([
                'message' => 'No tienes permiso para ver el estado de caja.',
            ], 403);
        }

        try {
            $cashBox = $this->cashBoxService->getOpenCashBox();

            if (!$cashBox) {
                return response()->json([
                    'message' => 'No hay una caja abierta.',
                    'data' => [
                        'status' => 'closed',
                        'opened_at' => null,
                        'initial_cash' => 0,
                        'total_income' => 0,
                        'total_expenses' => 0,
                        'current_cash' => 0,
                        'expected_closing' => 0,
                    ],
                ]);
            }

            $summary = $this->cashBoxService->getCashBoxSummary($cashBox->id);

            return response()->json([
                'message' => 'Estado de caja obtenido exitosamente.',
                'data' => $summary,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Error al obtener el estado de caja.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Open a new cash box
     * Permission: open_cash_box
     */
    public function openCashBox(Request $request): JsonResponse
    {
        if (!$request->user()->hasPermission('open_cash_box')) {
            return response()->json([
                'message' => 'No tienes permiso para abrir caja.',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'initial_cash' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Datos inválidos.',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $cashBox = $this->cashBoxService->openCashBox(
                userId: $request->user()->id,
                openingAmount: $request->initial_cash
            );

            $summary = $this->cashBoxService->getCashBoxSummary($cashBox->id);

            return response()->json([
                'message' => 'Caja abierta exitosamente.',
                'data' => $summary,
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Error al abrir la caja.',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Close the current cash box
     * Permission: close_cash_box
     */
    public function closeCashBox(Request $request): JsonResponse
    {
        if (!$request->user()->hasPermission('close_cash_box')) {
            return response()->json([
                'message' => 'No tienes permiso para cerrar caja.',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'final_cash' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Datos inválidos.',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $openCashBox = $this->cashBoxService->getOpenCashBox();
            
            if (!$openCashBox) {
                return response()->json([
                    'message' => 'No hay una caja abierta para cerrar.',
                ], 400);
            }

            $cashBox = $this->cashBoxService->closeCashBox(
                cashBoxId: $openCashBox->id,
                userId: $request->user()->id,
                closingAmount: $request->final_cash
            );

            $difference = $cashBox->closing_amount - $cashBox->expected_closing;

            return response()->json([
                'message' => 'Caja cerrada exitosamente.',
                'data' => [
                    'id' => $cashBox->id,
                    'expected_closing' => $cashBox->expected_closing,
                    'final_cash' => $cashBox->closing_amount,
                    'difference' => $difference,
                ],
            ]);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Error al cerrar la caja.',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Register an income
     * Permission: manage_cash_movements
     */
    public function registerIncome(Request $request): JsonResponse
    {
        if (!$request->user()->hasPermission('manage_cash_movements')) {
            return response()->json([
                'message' => 'No tienes permiso para registrar ingresos.',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:0.01',
            'description' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Datos inválidos.',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $cashBox = $this->cashBoxService->getOpenCashBox();
            
            if (!$cashBox) {
                return response()->json([
                    'message' => 'No hay una caja abierta.',
                ], 400);
            }

            $movement = $this->cashBoxService->registerIncome(
                cashBoxId: $cashBox->id,
                amount: $request->amount,
                description: $request->description,
                userId: $request->user()->id
            );

            return response()->json([
                'message' => 'Ingreso registrado exitosamente.',
                'data' => $movement,
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Error al registrar el ingreso.',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Register an expense
     * Permission: register_expense
     */
    public function registerExpense(Request $request): JsonResponse
    {
        if (!$request->user()->hasPermission('register_expense')) {
            return response()->json([
                'message' => 'No tienes permiso para registrar egresos.',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:0.01',
            'description' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Datos inválidos.',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $cashBox = $this->cashBoxService->getOpenCashBox();
            
            if (!$cashBox) {
                return response()->json([
                    'message' => 'No hay una caja abierta.',
                ], 400);
            }

            $movement = $this->cashBoxService->registerExpense(
                cashBoxId: $cashBox->id,
                amount: $request->amount,
                description: $request->description,
                userId: $request->user()->id
            );

            return response()->json([
                'message' => 'Egreso registrado exitosamente.',
                'data' => $movement,
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Error al registrar el egreso.',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get cash box movements
     * Permission: view_cash_box
     */
    public function getCashBoxMovements(Request $request): JsonResponse
    {
        if (!$request->user()->hasPermission('view_cash_box')) {
            return response()->json([
                'message' => 'No tienes permiso para ver movimientos de caja.',
            ], 403);
        }

        try {
            $cashBox = $this->cashBoxService->getOpenCashBox();
            
            if (!$cashBox) {
                return response()->json([
                    'message' => 'No hay una caja abierta.',
                    'data' => [],
                ]);
            }

            $filters = [
                'type' => $request->query('type'),
                'user_id' => $request->query('user_id'),
                'has_sale' => $request->query('has_sale'),
            ];

            $movements = $this->cashBoxService->getCashBoxMovements($cashBox->id, array_filter($filters));

            return response()->json([
                'message' => 'Movimientos de caja obtenidos exitosamente.',
                'data' => $movements,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Error al obtener los movimientos.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get all cash boxes
     * Permission: view_cash_box
     */
    public function index(Request $request): JsonResponse
    {
        if (!$request->user()->hasPermission('view_cash_box')) {
            return response()->json([
                'message' => 'No tienes permiso para ver cajas.',
            ], 403);
        }

        try {
            $filters = [
                'status' => $request->query('status'),
                'opened_by' => $request->query('opened_by'),
                'date_from' => $request->query('date_from'),
                'date_to' => $request->query('date_to'),
            ];

            $cashBoxes = $this->cashBoxService->getCashBoxes(array_filter($filters));

            return response()->json([
                'message' => 'Cajas obtenidas exitosamente.',
                'data' => $cashBoxes,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Error al obtener las cajas.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
