<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Report;
use Grimzy\LaravelMysqlSpatial\Types\Point;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{

    /**
     * vybrat z db okolni podnety v okruhu souradnic, ktere jsou predany v URL, podle vzdalenosti
     * vystupem bude kolekce 15 nebo klientem nahlaseneho poctu hlaseni, ktere jsou odeslany skrz JSON zpet
     * v pripade, ze uzivatel chce zobrazit vetsi pocet hlaseni nez predanych 15, posle se v requestu
     * ID posledniho podnetu v kolekce (ten ma nejvetsi vzdalenost od vychoziho bodu) a vybere se 15
     * dalsich podnetu, ktere maji vetsi vzdalenost nez dany bod, ale zaroven nejmensi vzdalenost od vychoziho bodu
     * povinne lat, ln
     * nepovinne skippedRecords, numberOfRecords
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {

        // Latitude and longitude are mandatory
        if (!$request->has('location')) {
            return response()->json([
                'error' => true,
                'message' => 'Missing lat and lng attributes!',
            ], 400);
        } else if ($request->numberOfRecords > 20){
            return response()->json([
                'error' => true,
                'message' => 'Maximum number of records is 20!',
            ], 400);
        }

        // Creating Point object from given coordinates

        $location = new Point($request->input('location.coordinates.0'), $request->input('location.coordinates.1'));

        // If request contains 'skippedRecords' means that we want to load more reports. Client wants to get
        // collection of reports which has bigger distance from given coordinates. So we need to skip some amount of found records.


        // TODO poradi neni spravne, doplnit casem o SRID

        if ($request->has('skippedRecords')) {

            $reports = Report::orderByDistance('location', $location, $direction = 'asc')->skip($request->skippedRecords)->limit(($request->has('$request->numberOfRecords')) ? $request->numberOfRecords : 10)->get();

        } else {

            $reports = Report::orderByDistance('location', $location, $direction = 'asc')->limit(($request->has('$request->numberOfRecords')) ? $request->numberOfRecords : 10)->get();

        }

        foreach ($reports as $report) {

            $point1 = 'POINT(' . $report->location->getLat() . ', ' . $report->location->getLng() . ')';
            $point2 = 'POINT(' . $location->getLat() . ', ' . $location->getLng() . ')';

            $value = DB::select('SELECT ST_Distance( ' . $point1 . ', ' . $point2 . ') AS distance');

            if($value != null)
                $report->distance = $value[0]->distance;
            else
                $report->distance = null;
        }

        return response()->json([
            'error' => false,
            'reports' => $reports,
        ], 200);
    }

    /**
     * Prijmu data (title, state, userNote, employeeNote, address, location)
     * Vytvorim objekt Report, zvaliduju vstupni hodnoty a vytvorim zaznam v db.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {

        $location = new Point($request->input('location.coordinates.0'), $request->input('location.coordinates.1'));

        // TODO zjisteni, zda se bod nachazi na danem uzemi (zatim pevne dane terr = 0)
        Report::create(array_merge($request->validate([
            'title' => ['required', 'string', 'max:255'],
            'state' => ['required', 'integer'],
            'category_id' => ['required', 'integer']
        ]), ['location' => $location, 'user_id' => Auth::id(), 'territory_id' => 0]));

        return response()->json([
            'error' => false,
        ], 200);
    }

    /**
     * @param Report $report
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Report $report)
    {
        return response()->json([
            'report' => $report
        ]);
    }

    /**
     * @param Request $request
     * @param Report $report
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, Report $report)
    {
        $report->update(array_merge($request->validate([
            'title' => ['required', 'string', 'max:255'],
            'state' => ['required', 'integer'],
            'location' => ['required']
        ])));

        return response()->json([
            'error' => false,
        ], 200);
    }

    /**
     * @param Report $report
     * @return \Illuminate\Http\JsonResponse
     * @throws \Exception
     */
    public function destroy(Report $report)
    {
        // user can delete only his reports
        if($report->user()->id == Auth::id()){
            $report->delete();

            return response()->json([
                'error' => false,
            ], 200);
        }

        return response()->json([
            'error' => true,
            'message' => 'Unauthenticated'
        ], 403);
    }
}