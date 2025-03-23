<?php

namespace App\Http\Controllers;

use App\Models\CredibilityOrg;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class LegalityDataCtrl extends Controller
{
    public function create(Request $req)
    {
        $legalityData = $req->org->credibilityData()->first();
        if ($legalityData) {
            return $this->update($req);
        }

        $arrayValidator = [
            "type_legality" => "required|string",
            "tax_id_number" => "required|string",
            "tax_image" => "required|image|max:2048",
            'confirm_true' => 'required|boolean'
        ];
        if ($req->type_legality === 'Perusahaan') {
            $arrayValidator += [
                "company_name" => "required|string",
                "business_entity" => "required|string",
                "company_address" => "required|string"
            ];
        }else{
            $arrayValidator += [
                "pic_name" => "required|string",
                "nic_number" => "required|string",
                "nic_image" => "required|image|max:2048",
            ];
        }
        $validator = Validator::make($req->all(), $arrayValidator);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 403);
        }

        if(!$req->confirm_true){
            return response()->json(["error" => "Plase confirm your legality data is true"], 403);
        }

        $namePhoto = '';

        // $namePhotoTax = pathinfo($req->file('tax_image')->getClientOriginalName(), PATHINFO_FILENAME);
        $namePhotoTax = BasicFunctional::randomStr(5) . '_' . time() . '.' . $req->file('tax_image')->getClientOriginalExtension();
        $req->file('tax_image')->storeAs('public/legality_datas', $namePhotoTax);
        $namePhotoTax = '/storage/legality_datas/' . $namePhotoTax;

        if($req->type_legality === 'Individu'){
            // $namePhoto = pathinfo($req->file('nic_image')->getClientOriginalName(), PATHINFO_FILENAME);
            $namePhoto = BasicFunctional::randomStr(5) . '_' . time() . '.' . $req->file('nic_image')->getClientOriginalExtension();
            $req->file('nic_image')->storeAs('public/legality_datas', $namePhoto);
            $namePhoto = '/storage/legality_datas/' . $namePhoto;
        }

        $datas = CredibilityOrg::create([
            "org_id" => $req->org->id,
            "type_legality" => $req->type_legality,
            "tax_id_number" => $req->tax_id_number,
            "tax_image" => $namePhotoTax,

            "company_name" => $req->company_name ? $req->company_name : " ",
            "address" => $req->company_address ? $req->company_address : ' ',
            'business_entity' => $req->business_entity ? $req->business_entity : " ",
            
            'pic_name' => $req->pic_name ? $req->pic_name : " ",
            'pic_nic' => $req->nic_number ? $req->nic_number : ' ',
            'pic_nic_image' => $namePhoto !== '' ? $namePhoto : ' ',

            'company_phone' => " ",
            'status' => false,
        ]);
        return response()->json(["data" => $datas], 201);
    }

    public function update(Request $req)
    {
        $dataLegality = CredibilityOrg::where('org_id', $req->org->id);
        if (!$dataLegality->first()) {
            return $this->create($req);
        }
        
        $arrayValidator = [
            "type_legality" => "required|string",
            "tax_id_number" => "required|string",
            "tax_image" => "image|max:2048",
            'confirm_true' => 'required|boolean'
        ];
        if ($req->type_legality === 'Perusahaan') {
            $arrayValidator += [
                "company_name" => "required|string",
                "business_entity" => "required|string",
                "company_address" => "required|string"
            ];
        }else{
            $arrayValidator += [
                "pic_name" => "required|string",
                "nic_number" => "required|string",
                "nic_image" => "image|max:2048",
            ];
        }
        $validator = Validator::make($req->all(), $arrayValidator);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 403);
        }

        if(!$req->confirm_true){
            return response()->json(["error" => "Plase confirm your legality data is true"], 403);
        }
        
        
        $namePhoto = $dataLegality->first()->pic_nic_image;
        if ($req->hasFile('nic_image') && $req->type_legality === 'Individu') {
            // $namePhoto = pathinfo($req->file('nic_image')->getClientOriginalName(),  PATHINFO_FILENAME);
            $namePhoto = BasicFunctional::randomStr(5) . '_' . time() . '.' . $req->file('nic_image')->getClientOriginalExtension();
            $req->file('nic_image')->storeAs('public/legality_datas', $namePhoto);
            $namePhoto = '/storage/legality_datas/' . $namePhoto;
            Storage::delete('public/legality_datas/' . explode('/', $dataLegality->first()->pic_nic_image)[3]);
        }
        $namePhotoTax = $dataLegality->first()->tax_image;
        if ($req->hasFile('tax_image')) {
            // $namePhotoTax = pathinfo($req->file('tax_image')->getClientOriginalName(),  PATHINFO_FILENAME);
            $namePhotoTax = BasicFunctional::randomStr(5) . '_' . time() . '.' . $req->file('tax_image')->getClientOriginalExtension();
            $req->file('tax_image')->storeAs('public/legality_datas', $namePhotoTax);
            $namePhotoTax = '/storage/legality_datas/' . $namePhotoTax;
            Storage::delete('public/legality_datas/' . explode('/', $dataLegality->first()->tax_image)[3]);
        }
        $dataLegality->update([
            "org_id" => $req->org->id,
            "type_legality" => $req->type_legality,
            "tax_id_number" => $req->tax_id_number,
            "tax_image" => $namePhotoTax,

            "company_name" => $req->company_name ? $req->company_name : " ",
            "address" => $req->company_address ? $req->company_address : ' ',
            'business_entity' => $req->business_entity ? $req->business_entity : " ",
            
            'pic_name' => $req->pic_name ? $req->pic_name : " ",
            'pic_nic' => $req->nic_number ? $req->nic_number : ' ',
            'pic_nic_image' => $namePhoto !== '' ? $namePhoto : ' ',

            'company_phone' => " ",
            'status' => false,
        ]);
        return response()->json(["data" => $dataLegality->first()], 202);
    }

    public function getLegality(Request $req)
    {
        $data = null;
        if ($req->org) {
            $data = $req->org;
            $data->legality_data = $data->credibilityData()->first();
        } else {
            $org = Organization::where('id', $req->org_id)->first();
            if (!$org) {
                return response()->json(["error" => "Organization data not found"], 404);
            }
            $data = $org;
            $data->legality_data = $data->credibilityData()->first();
        }
        return response()->json(["data" => $data], 200);
    }

    // Access by admin only
    public function getLegalities()
    {
        $orgs = Organization::all();
        $verifiedLegalities = [];
        $unverifiedLegalities = [];
        foreach ($orgs as $org) {
            $org->legality_data = $org->credibilityData()->first();
            if ($org->legality_data && $org->legality_data->status == true) {
                array_push($verifiedLegalities, $org);
            } else if ($org->legality_data && $org->legality_data->status == false) {
                array_push($unverifiedLegalities, $org);
            }
        }
        return response()->json(["verified" => $verifiedLegalities, "unverified" => $unverifiedLegalities], 200);
    }

    public function changeState(Request $req)
    {
        $data = CredibilityOrg::where('id', $req->id);
        if (!$data->first()) {
            return response()->json(["error" => "Data not found"], 404);
        }
        $data->update([
            "status" => $req->status == true ? true : false
        ]);
        return response()->json(["data" => $data->first()], 202);
    }

    public function delete(Request $req)
    {
        $data = CredibilityOrg::where('id', $req->id);
        if (!$data->first()) {
            return response()->json(["error" => "Data not found"], 404);
        }
        $deleted = $data->delete();
        return response()->json(["deleted" => $deleted], 202);
    }
}
