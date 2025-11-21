<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\OnBoarding;

class RestaurantAppSettingController extends Controller
{
    public function getOnBoardingList($type)
    {
        try {
            $data = OnBoarding::where("type", $type)->get();

            return response()->json([
                "success" => true,
                "data" => $data
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                "success" => false,
                "message" => $e->getMessage()
            ], 500);
        }
    }



}
