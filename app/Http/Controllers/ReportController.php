<?php

namespace App\Http\Controllers;

use App\ModuleData;
use App\ReportPhoto;
use App\Territory;
use App\User;
use Exception;
use Illuminate\Http\Request;
use App\Report;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    /**
     * Zobrazení seznamu podnětů
     *
     * @param Territory $territory
     * @return Response
     */
    public function index(Territory $territory)
    {
        // podněty si mohou zobrazovat pouze zaměstnanci dané samosprávy
        if ($territory->admin_id === Auth::id() || $territory->approver_id === Auth::id() || $territory->supervisor()->where('user_id', Auth::id())->first() || $territory->problemSolver()->where('user_id', Auth::id())->first()) {

            // výběr podnětů z databáze
            $reports = DB::table('reports')->select('id', 'title', 'state', 'userNote', 'user_id', 'responsible_user_id', 'category_id', 'created_at')->where('territory_id', $territory->id)->get();

            return response()->json([
                "reports" => $reports
            ], 200);
        } else {
            return abort('403');
        }
    }


    /**
     * Uložení nového podnětu
     *
     * @param Request $request
     * @return Response
     */
    public function store(Request $request)
    {
        abort(404);
    }

    /**
     * Zobrazení detailu konkrétního podnětu
     *
     * @param Territory $territory
     * @param Report $report
     * @return void
     */
    public function show(Territory $territory, Report $report)
    {
        // detail podnětu si mohou zobrazovat pouze zaměstnanci dané samosprávy
        if ($territory->admin_id === Auth::id() || $territory->approver_id === Auth::id() || $territory->supervisor()->where('user_id', Auth::id())->first() || $territory->problemSolver()->where('user_id', Auth::id())->first()) {

            // vybrat url adresy fotografií, které náleží podnětu
            $photos = ReportPhoto::select('url')->where('report_id', '=', $report->id)->get();

            $arrayPhotos = array();
            for ($i = 0; $i < count($photos); $i++) {
                array_push($arrayPhotos, json_decode($photos[$i])->url);
            }
            $report->photos = $arrayPhotos;

            // vybrat vlastníka podnětu
            $report->user = $report->user()->first();

            // vybrat zodpovědnou osobu (pokud existuje)
            $report->responsible = User::find($report->responsible_user_id);

            $report->location = (object)[
                'lat' => $report->location->getLat(),
                'lng' => $report->location->getLng(),
            ];

            // přidání dat modulů k podnětu (pokud existují)
            $report->moduleData = $report->moduleData()
                ->join('modules', 'modules.id', '=', 'module_data.module_id')
                ->get(['module_data.id', 'modules.name']);

            foreach ($report->moduleData as $moduleData) {
                $moduleData->inputData = $moduleData->inputData()->join('inputs', 'inputs.id', '=', 'input_data.input_id')
                    ->get(['input_data.id', 'inputs.title', 'input_data.data']);
            }

            unset($report['user_id']);

            unset($territory['updated_at']);

            return response()->json([
                "report" => $report
            ], 200);
        } else {
            return abort('403');
        }

    }


    /**
     * Aktualizace konkrétního podnětu
     *
     * @param Territory $territory
     * @param Report $report
     * @return void
     */
    public function update(Territory $territory, Report $report)
    {
        // podněty mohou aktualizovat pouze admin, schvalovatel nebo jeho řešitel
        if ($territory->admin_id === Auth::id() || $territory->approver_id === Auth::id() || $territory->problemSolver()->where('user_id', Auth::id())->where('user_id', $report->responsible_user_id)->first()) {

            // validace a aktualizace podnětu
            $report->update(array_merge(request()->validate([
                'title' => ['required', 'string', 'max:255'],
                'category_id' => ['required', 'integer'],
                'state' => ['required', 'integer', 'min:0', 'max:3'],
                'userNote' => ['required', 'string', 'max:255'],
                'employeeNote' => ['nullable', 'string', 'max:255'],
                'responsible_user_id' => ['nullable', 'integer']
            ])));

            $photos = ReportPhoto::select('url')->where('report_id', '=', $report->id)->get();

            $arrayPhotos = array();
            for ($i = 0; $i < count($photos); $i++) {
                array_push($arrayPhotos, json_decode($photos[$i])->url);
            }
            $report->photos = $arrayPhotos;
            $report->user = $report->user()->first();
            $report->responsible = User::find($report->responsible_user_id);

            $report->location = (object)[
                'lat' => $report->location->getLat(),
                'lng' => $report->location->getLng(),
            ];

            $report->moduleData = $report->moduleData()
                ->join('modules', 'modules.id', '=', 'module_data.module_id')
                ->get(['module_data.id', 'modules.name']);

            foreach ($report->moduleData as $moduleData) {
                $moduleData->inputData = $moduleData->inputData()->join('inputs', 'inputs.id', '=', 'input_data.input_id')
                    ->get(['input_data.id', 'inputs.title', 'input_data.data']);
            }

            unset($report['user_id']);

            unset($territory['updated_at']);

            return response()->json([
                "report" => $report
            ], 200);

        } else {
            return abort('403');
        }

    }

    /**
     * Odstranění podnětu
     *
     * @param Territory $territory
     * @param Report $report
     * @return void
     * @throws Exception
     */
    public function destroy(Territory $territory, Report $report)
    {
        // podněty mohou být odstraněny pouze administrátorem, schvalovatelem nebo jeho řešitelem
        if ($territory->admin_id === Auth::id() || $territory->approver_id === Auth::id() || $territory->problemSolver()->where('user_id', Auth::id())->where('user_id', $report->responsible_user_id)->first()) {

            $report->delete();

            return response()->json([
                "error" => false
            ], 200);

        } else {
            return abort('403');
        }
    }
}
