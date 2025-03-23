<?php
namespace App\Http\Controllers;

use App\Models\Admin;
use App\Models\Category;
use App\Models\City;
use App\Models\Event;
use App\Models\FrontBanner;
use App\Models\FrontBannerLarge;
use App\Models\Organization;
use App\Models\OrgType;
use App\Models\Payment;
use App\Models\ProfitSetting;
use App\Models\Purchase;
use App\Models\RefundSetting;
use App\Models\SelectedActivity;
use App\Models\SelectedActivityDatas;
use App\Models\SelectedEvent;
use App\Models\SelectedEventDatas;
use App\Models\SpecialDay;
use App\Models\SpecialDayEvents;
use App\Models\Spotlight;
use App\Models\SpotlightEvents;
use App\Models\Topic;
use App\Models\TopicActivity;
use App\Models\User;
use App\Models\ViralCity;
use DateTime;
use DateTimeZone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class AdminCtrl extends Controller
{
    //------------- Control by admin for sekunder data -----------------
    public function createCategory(Request $req)
    {
        $validator = Validator::make($req->all(), [
            "name"  => "required|unique:categories|string",
            "photo" => "required|image|max:2048",
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 403);
        }
        // $fileName = pathinfo($req->file('photo')->getClientOriginalName(), PATHINFO_FILENAME);
        $fileName = BasicFunctional::randomStr(5) . '_' . time() . '.' . $req->file('photo')->getClientOriginalExtension();
        $req->file('photo')->storeAs('public/categories_images', $fileName);
        $fileName = '/storage/categories_images/' . $fileName;
        $category = Category::create([
            'name'     => $req->name,
            'photo'    => $fileName,
            'priority' => count(Category::all()),
        ]);
        return response()->json(["data" => $category], 201);
    }

    public function deleteCategory(Request $req)
    {
        $validator = Validator::make($req->all(), [
            "cat_id" => "required|numeric",
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 403);
        }
        $category     = Category::where('id', $req->cat_id);
        $categoryData = $category->first();
        if (! $categoryData) {
            return response()->json(["error" => "Data not found"], 404);
        }
        Storage::delete('/public/categories_images/' . explode('/', $categoryData->photo)[3]);
        foreach (Category::where('priority', '>', $categoryData->priority)->get() as $cat) {
            Category::where('id', $cat->id)->update(["priority" => intval($cat->priority) - 1]);
        }
        $category->delete();
        return response()->json(["categories" => Category::orderBy('priority', 'DESC')->get()], 202);
    }

    public function setPriorityPlusCat(Request $req)
    {
        $validator = Validator::make($req->all(), [
            "cat_id" => "required|numeric",
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 403);
        }
        $category     = Category::where('id', $req->cat_id);
        $categoryData = $category->first();
        if (! $categoryData) {
            return response()->json(["error" => "Data not found"], 404);
        }
        if ((count(Category::all()) - 1) > $categoryData->priority) {
            Category::where('priority', (intval($categoryData->priority) + 1))->update([
                "priority" => $categoryData->priority,
            ]);
            $category->update([
                "priority" => intval($categoryData->priority) + 1,
            ]);
        }
        return response()->json(["data" => Category::orderBy('priority', 'DESC')->get()], 202);
    }

    public function setPriorityMinCat(Request $req)
    {
        $validator = Validator::make($req->all(), [
            "cat_id" => "required|numeric",
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 403);
        }
        $category     = Category::where('id', $req->cat_id);
        $categoryData = $category->first();
        if (! $categoryData) {
            return response()->json(["error" => "Data not found"], 404);
        }
        if ($categoryData->priority > 0) {
            Category::where('priority', (intval($categoryData->priority) - 1))->update([
                "priority" => $categoryData->priority,
            ]);
            $category->update([
                "priority" => intval($categoryData->priority) - 1,
            ]);
        }
        return response()->json(["data" => Category::orderBy('priority', 'DESC')->get()], 202);
    }

    public function categories(Request $req)
    {
        return response()->json(["categories" => Category::orderBy('priority', 'DESC')->get()], 200);
    }
    // ===============================================================
    public function createTopic(Request $req)
    {
        $validator = Validator::make($req->all(), [
            "name" => "required|unique:topics|array",
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 403);
        }
        $topics = [];
        foreach ($req->name as $name) {
            $topics[] = Topic::create([
                "name" => $name,
            ]);
        }
        return response()->json(["data" => $topics], 201);
    }

    public function deleteTopic(Request $req)
    {
        $deleted = Topic::where('id', $req->topic_id)->delete();
        return response()->json(["deleted" => $deleted], $deleted == 0 ? 404 : 202);
    }

    public function topics(Request $req)
    {
        return response()->json(["topics" => Topic::all()], 200);
    }
    // ==============================================================
    public function createTopicAct(Request $req)
    {
        $validator = Validator::make($req->all(), [
            "name"     => "required|unique:topic_activities|array",
            "category" => "required|string",
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 403);
        }
        $topics = [];
        foreach ($req->name as $name) {
            $topics[] = TopicActivity::create([
                "name"     => $name,
                "category" => $req->category,
            ]);
        }
        return response()->json(["data" => $topics], 201);
    }

    public function deleteTopicAct(Request $req)
    {
        $deleted = TopicActivity::where('id', $req->topic_id)->delete();
        return response()->json(["deleted" => $deleted], $deleted == 0 ? 404 : 202);
    }

    public function topicsAct(Request $req)
    {
        return response()->json(["topics" => TopicActivity::all()->groupBy('category')], 200);
    }
    // ==============================================================
    public function createOrgType(Request $req)
    {
        $validator = Validator::make($req->all(), [
            "name" => "required|unique:org_types|array",
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 403);
        }
        $orgType = [];

        foreach ($req->name as $type) {
            $orgType[] = orgType::create([
                "name" => $type,
            ]);
        }

        return response()->json(["data" => $orgType], 201);
    }

    public function deleteOrgType(Request $req)
    {
        $deleted = OrgType::where('id', $req->org_type_id)->delete();
        return response()->json(["deleted" => $deleted], $deleted == 0 ? 404 : 202);
    }

    public function orgTypes(Request $req)
    {
        return response()->json(["org_types" => OrgType::all()], 200);
    }
    // ==============================================================
    public function createCity(Request $req)
    {
        $validator = Validator::make($req->all(), [
            "name"  => "required|string|unique:cities",
            "photo" => "required|image|max:2048",
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 403);
        }
        // $fileName = pathinfo($req->file('photo')->getClientOriginalName(), PATHINFO_FILENAME);
        $fileName = BasicFunctional::randomStr(5) . '_' . time() . '.' . $req->file('photo')->getClientOriginalExtension();
        $req->file('photo')->storeAs('public/city_images', $fileName);
        $fileName = '/storage/city_images/' . $fileName;
        $city     = City::create([
            "name"     => $req->name,
            "photo"    => $fileName,
            'priority' => count(City::all()),
        ]);
        return response()->json(["data" => $city], 201);
    }

    public function deleteCity(Request $req)
    {
        $validator = Validator::make($req->all(), [
            "city_id" => "required|numeric",
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 403);
        }
        $city     = City::where('id', $req->city_id);
        $cityData = $city->first();
        if (! $cityData) {
            return response()->json(["error" => "Data not found"], 404);
        }
        Storage::delete('public/city_images/' . explode('/', $cityData->photo)[3]);
        foreach (City::where('priority', '>', $cityData->priority)->get() as $ct) {
            City::where('id', $ct->id)->update([
                "priority" => intval($ct->priority) - 1,
            ]);
        }
        $city->delete();
        return response()->json(['cities' => City::orderBy('priority', 'DESC')->get()], 202);
    }

    public function setPriorityPlusCity(Request $req)
    {
        $validator = Validator::make($req->all(), [
            "city_id" => "required|numeric",
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 403);
        }
        $city     = City::where('id', $req->city_id);
        $cityData = $city->first();
        if (! $cityData) {
            response()->json(["error" => "Data not found"], 404);
        }
        if ($cityData->priority < (count(City::all()) - 1)) {
            City::where('priority', (intval($cityData->priority) + 1))->update([
                "priority" => $cityData->priority,
            ]);
            $city->update([
                "priority" => intval($cityData->priority) + 1,
            ]);
        }
        return response()->json(["cities" => City::orderBy('priority', 'DESC')->get()], 202);
    }

    public function setPriorityMinCity(Request $req)
    {
        $validator = Validator::make($req->all(), [
            "city_id" => "required|numeric",
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 403);
        }
        $city     = City::where('id', $req->city_id);
        $cityData = $city->first();
        if (! $cityData) {
            return response()->json(["error" => "Data not found"], 404);
        }
        if ($cityData->priority > 0) {
            City::where('priority', (intval($cityData->priority) - 1))->update([
                "priority" => $cityData->priority,
            ]);
            $city->update([
                "priority" => intval($cityData->priority) - 1,
            ]);
        }
        return response()->json(["cities" => City::orderBy('priority', 'DESC')->get()], 202);
    }

    public function cities(Request $req)
    {
        return response()->json(["cities" => City::orderBy('priority', 'DESC')->get()], 200);
    }
    // ==============================================================
    public function createFrontBanner(Request $req)
    {
        $validator = Validator::make($req->all(), [
            "name"   => "required|string",
            "url"    => "required|active_url",
            "banner" => "required|image|max:2048",
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 403);
        }
        // $fileName = pathinfo($req->file('banner')->getClientOriginalName(), PATHINFO_FILENAME);
        $fileName = BasicFunctional::randomStr(5) . '_' . time() . '.' . $req->file('banner')->getClientOriginalExtension();
        $req->file('banner')->storeAs('public/banner_images', $fileName);
        $fileName = '/storage/banner_images/' . $fileName;
        $banner   = null;
        if (isset($req->banner_large) && $req->banner_large == true) {
            $banner = FrontBannerLarge::create([
                "name"     => $req->name,
                "url"      => $req->url,
                "photo"    => $fileName,
                "priority" => count(FrontBannerLarge::all()),
            ]);
        } else {
            $banner = FrontBanner::create([
                "name"     => $req->name,
                "url"      => $req->url,
                "photo"    => $fileName,
                "priority" => count(FrontBanner::all()),
            ]);
        }
        return response()->json(["data" => $banner], 201);
    }

    public function deleteFirstBanner(Request $req)
    {
        $validator = Validator::make($req->all(), [
            "f_banner_id" => "required|numeric",
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 403);
        }
        $fBanner     = isset($req->banner_large) && $req->banner_large == true ? FrontBannerLarge::where('id', $req->f_banner_id) : FrontBanner::where('id', $req->f_banner_id);
        $fBannerData = $fBanner->first();
        if (! $fBannerData) {
            return response()->json(["error" => "Data not found"], 404);
        }
        Storage::delete('/public/banner_images/' . explode('/', $fBannerData->photo)[3]);
        foreach (isset($req->banner_large) && $req->banner_large == true ? FrontBannerLarge::where('priority', '>', $fBannerData->priority)->get() : FrontBanner::where('priority', '>', $fBannerData->priority)->get() as $fBan) {
            isset($req->banner_large) && $req->banner_large == true ? FrontBannerLarge::where('id', $fBan->id)->update(["priority" => intval($fBan->priority) - 1]) : FrontBanner::where('id', $fBan->id)->update(["priority" => intval($fBan->priority) - 1]);
        }
        $fBanner->delete();
        return response()->json(["f_banners" => isset($req->banner_large) && $req->banner_large == true ? FrontBannerLarge::orderBy('priority', 'DESC')->get() : FrontBanner::orderBy('priority', 'DESC')->get()], 202);
    }

    public function setPriorityPlusFBanner(Request $req)
    {
        $validator = Validator::make($req->all(), [
            "f_banner_id" => "required|numeric",
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 403);
        }
        $fBanner     = isset($req->banner_large) && $req->banner_large == true ? FrontBannerLarge::where('id', $req->f_banner_id) : FrontBanner::where('id', $req->f_banner_id);
        $fBannerData = $fBanner->first();
        if (! $fBannerData) {
            return response()->json(["error" => "Data not found"], 404);
        }
        if ($fBannerData->priority < (count(isset($req->banner_large) && $req->banner_large == true ? FrontBannerLarge::all() : FrontBanner::all()) - 1)) {
            isset($req->banner_large) && $req->banner_large == true ? FrontBannerLarge::where('priority', (intval($fBannerData->priority) + 1))->update([
                "priority" => $fBannerData->priority,
            ]) : FrontBanner::where('priority', (intval($fBannerData->priority) + 1))->update([
                "priority" => $fBannerData->priority,
            ]);
            $fBanner->update([
                "priority" => intval($fBannerData->priority) + 1,
            ]);
        }
        return response()->json(["f_banners" => isset($req->banner_large) && $req->banner_large == true ? FrontBannerLarge::orderBy('priority', 'DESC')->get() : FrontBanner::orderBy('priority', 'DESC')->get()], 202);
    }

    public function setPriorityMinFBanner(Request $req)
    {
        $validator = Validator::make($req->all(), [
            "f_banner_id" => "required|numeric",
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 403);
        }
        $fBanner     = isset($req->banner_large) && $req->banner_large == true ? FrontBannerLarge::where('id', $req->f_banner_id) : FrontBanner::where('id', $req->f_banner_id);
        $fBannerData = $fBanner->first();
        if (! $fBanner) {
            return response()->json(["error" => "Data not found"], 404);
        }
        if ($fBannerData->priority > 0) {
            isset($req->banner_large) && $req->banner_large == true ? FrontBannerLarge::where('priority', (intval($fBannerData->priority) - 1))->update([
                "priority" => $fBannerData->priority,
            ]) : FrontBanner::where('priority', (intval($fBannerData->priority) - 1))->update([
                "priority" => $fBannerData->priority,
            ]);
            $fBanner->update([
                "priority" => intval($fBannerData->priority) - 1,
            ]);
        }
        return response()->json(["f_banners" => isset($req->banner_large) && $req->banner_large == true ? FrontBannerLarge::orderBy('priority', 'DESC')->get() : FrontBanner::orderBy('priority', 'DESC')->get()], 202);
    }

    public function frontBanners(Request $req)
    {
        return response()->json(["f_banners" => isset($req->banner_large) && $req->banner_large == true ? FrontBannerLarge::orderBy('priority', 'DESC')->get() : FrontBanner::orderBy('priority', 'DESC')->get()], 200);
    }
    // ==============================================================
    public function createSpotlight(Request $req)
    {
        $validator = Validator::make($req->all(), [
            "title"     => "required|string",
            "sub_title" => "required|string",
            "banner"    => "required|image|max:2048",
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 403);
        }
        // $nameBanner = pathinfo($req->file('banner')->getClientOriginalName(), PATHINFO_FILENAME);
        $nameBanner = BasicFunctional::randomStr(5) . '_' . time() . '.' . $req->file('banner')->getClientOriginalExtension();
        $req->file('banner')->storeAs('public/spotlight_banners', $nameBanner);
        $nameBanner = '/storage/spotlight_banners/' . $nameBanner;
        // if ($req->view) {
        //     DB::table('spotlights')->update(['view' => false]);
        // }
        $spotlight = Spotlight::create([
            'title'     => $req->title,
            'sub_title' => $req->sub_title,
            'banner'    => $nameBanner,
            'view'      => $req->view ? true : false,
        ]);
        return response()->json(["spotlight" => $spotlight], 201);
    }

    public function updateSpotlight(Request $req)
    {
        $validator = Validator::make($req->all(), [
            "title"     => "required|string",
            "sub_title" => "required|string",
            "banner"    => "image|max:2048",
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 403);
        }
        $spotlight = Spotlight::where('id', $req->spotlight_id);
        if (! $spotlight->first()) {
            return response()->json(["error" => "Spotlight data not found"], 404);
        }
        $nameBanner = $spotlight->first()->banner;
        if ($req->hasFile('banner')) {
            // $nameBanner = pathinfo($req->file('banner')->getClientOriginalName(), PATHINFO_FILENAME);
            $nameBanner = BasicFunctional::randomStr(5) . '_' . time() . '.' . $req->file('banner')->getClientOriginalExtension();
            $req->file('banner')->storeAs('public/spotlight_banners', $nameBanner);
            $nameBanner = '/storage/spotlight_banners/' . $nameBanner;
            Storage::delete('public/spotlight_banners/' . explode('/', $spotlight->first()->banner)[3]);
        }
        $updated = $spotlight->update([
            'title'     => $req->title,
            'sub_title' => $req->sub_title,
            'banner'    => $nameBanner,
        ]);
        return response()->json(["updated" => $updated], 202);
    }

    public function setViewSpotlight(Request $req)
    {
        $spotlight = Spotlight::where('id', $req->spotlight_id);
        if (! $spotlight->first()) {
            return response()->json(["error" => "Spotlight data not found"], 404);
        }
        // DB::table('spotlights')->update(['view' => false]);
        $updated = $spotlight->update(['view' => $spotlight->first()->view == true ? false : true]);
        return response()->json(["updated" => $updated], 202);
    }

    public function deleteSpotlight(Request $req)
    {
        $spotlight = Spotlight::where('id', $req->spotlight_id);
        if (! $spotlight->first()) {
            return response()->json(["error" => "Spotlight data not found"], 404);
        }
        Storage::delete('public/spotlight_banners/' . explode('/', $spotlight->first()->banner)[3]);
        $deleted = $spotlight->delete();
        return response()->json(['deleted' => $deleted], 202);
    }

    public function addEventSpotlight(Request $req)
    {
        // add event in seleccted spotlight
        $validator = Validator::make($req->all(), [
            'spotlight_id' => 'required|string',
            'event_id'     => 'required|array',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 403);
        }
        $spotlight = Spotlight::where('id', $req->spotlight_id);
        if (! $spotlight->first()) {
            return response()->json(["error" => "Spotlight data not found"], 404);
        }
        $data = [];
        foreach ($req->event_id as $eventId) {
            $event = Event::where('id', $eventId)->where('is_publish', 2)->where('visibility', true)->where('deleted', 0)->where('end_date', '>=', new DateTime('now', new DateTimeZone('Asia/Jakarta')));
            if (! $event->first()) {
                return response()->json(["error" => "Event data not found"], 404);
            }
            if (SpotlightEvents::where('spotlight_id', $req->spotlight_id)->where('event_id', $eventId)->first()) {
                return response()->json(["error" => "Event data in one spotlight can't duplicated"], 403);
            }
            $countEventSpotlight = count(SpotlightEvents::where('spotlight_id', $req->spotlight_id)->get());
            $data[]              = SpotlightEvents::create([
                'spotlight_id' => $req->spotlight_id,
                'event_id'     => $eventId,
                'priority'     => $countEventSpotlight + 1,
            ]);
        }

        return response()->json(["data" => $data], 201);
    }

    public function deleteEventSpotlight(Request $req)
    {
        // delete event in selected spotlight
        $target = SpotlightEvents::where('id', $req->spotlight_event_id);
        if (! $target->first()) {
            return response()->json(["error" => 'Event data not found'], 404);
        }
        foreach (SpotlightEvents::where('spotlight_id', $target->first()->spotlight_id)->where('priority', '>', intval($target->first()->priority))->get() as $spotEvent) {
            SpotlightEvents::where('id', $spotEvent->id)->update(["priority" => intval($spotEvent->priority) - 1]);
        }
        $spotlightId = $target->first()->spotlight_id;
        $target->delete();
        return response()->json(["event_spotlights" => SpotlightEvents::where('spotlight_id', $spotlightId)->orderBy('priority', 'DESC')->with(['event.org', 'event.tickets'])->get()], 202);
    }

    public function addPrioEventSpotlight(Request $req)
    {
        $target = SpotlightEvents::where('id', $req->spotlight_event_id);
        if (! $target->first()) {
            return response()->json(["error" => 'Event data not found'], 404);
        }
        $countEventSpotlight = count(SpotlightEvents::where('spotlight_id', $target->first()->spotlight_id)->get());
        if (intval($target->first()->priority) < $countEventSpotlight) {
            SpotlightEvents::where('spotlight_id', $target->first()->spotlight_id)->where('priority', intval($target->first()->priority) + 1)->update([
                'priority' => $target->first()->priority,
            ]);
            $target->update(['priority' => intval($target->first()->priority) + 1]);
        }
        return response()->json(["event_spotlights" => SpotlightEvents::where('spotlight_id', $target->first()->spotlight_id)->orderBy('priority', 'DESC')->get()], 202);
    }

    public function minPrioEventSpotlight(Request $req)
    {
        $target = SpotlightEvents::where('id', $req->spotlight_event_id);
        if (! $target->first()) {
            return response()->json(["error" => 'Event data not found'], 404);
        }
        if (intval($target->first()->priority) > 1) {
            SpotlightEvents::where('spotlight_id', $target->first()->spotlight_id)->where('priority', intval($target->first()->priority) - 1)->update([
                'priority' => $target->first()->priority,
            ]);
            $target->update([
                'priority' => intval($target->first()->priority) - 1,
            ]);
        }
        return response()->json(["event_spotlights" => SpotlightEvents::where('spotlight_id', $target->first()->spotlight_id)->orderBy('priority', 'DESC')->get()], 202);
    }

    private function spotlightData($spotlightId = null)
    {
        $spotlight = null;
        if ($spotlightId !== null) {
            $spotlight = Spotlight::where('id', $spotlightId)->first();
        } else {
            $spotlight = Spotlight::where('view', true)->first();
        }
        if (! $spotlight) {
            return ["error" => 'Event data not found', "status" => 404];
        }
        $eventsTargets = $spotlight->events()->get();
        $events        = [];
        foreach ($eventsTargets as $key => $evtTraget) {
            $event = $evtTraget->event()->first();
            if ($event->deleted === 0 && $event->is_publish === 2 && $event->visibility == 1 && new DateTime($event->end_date . " " . $event->end_time, new DateTimeZone('Asia/Jakarta')) >= new DateTime('now', new DateTimeZone('Asia/Jakarta'))) {
                $event->id_data        = $evtTraget->id;
                $event->available_days = $event->availableDays()->get();
                $event->org            = $event->org()->first();
                $event->org->legality  = $event->org->credibilityData()->first();
                $event->tickets        = $event->tickets()->orderBy('price', 'ASC')->get();
                $events[]              = $event;
            }
        }
        return ["data" => $spotlight, "events" => $events, "status" => 200];
    }

    public function getSpotlight(Request $req)
    {
        $data = $this->spotlightData($req->spotlight_id);
        if ($data["status"] !== 200) {
            return response()->json(["error" => $data["error"]], $data["status"]);
        }
        return response()->json(["spotlight" => ["data" => $data["data"], "events" => $data["events"]]], $data["status"]);
    }

    public function getActiveSpotlights()
    {
        $spotlights = Spotlight::where('view', true)->get();
        $data       = [];
        foreach ($spotlights as $spotlight) {
            $data[] = $this->spotlightData($spotlight->id);
        }
        return response()->json(["spotlights" => $data], 200);
    }

    public function listSpotlights()
    {
        $spotlights = Spotlight::all();
        $data       = [];
        foreach ($spotlights as $spotlight) {
            $data[] = $this->spotlightData($spotlight->id);
        }
        return response()->json(["spotlights" => $data], 200);
    }
    // ==============================================================
    public function createSpcDay(Request $req)
    {
        if (! $req->title) {
            return response()->json(["error" => 'Title field is required'], 403);
        }
        if ($req->view) {
            DB::table('special_days')->update(["view" => false]);
        }
        $specialDay = SpecialDay::create([
            "title" => $req->title,
            "view"  => $req->view ? true : false,
        ]);
        return response()->json(["special_day" => $specialDay], 201);
    }

    public function updateSpcDay(Request $req)
    {
        if (! $req->title) {
            return response()->json(["error" => "Title field iis required"], 403);
        }
        $specialDay = SpecialDay::where('id', $req->special_day_id);
        if (! $specialDay->first()) {
            return response()->json(["error" => "Data not found"], 404);
        }
        $updated = $specialDay->update([
            "title" => $req->title,
        ]);
        return response()->json(["updated" => $updated], 202);
    }

    public function setViewSpcDay(Request $req)
    {
        $specialDay = SpecialDay::where('id', $req->special_day_id);
        if (! $specialDay->first()) {
            return response()->json(["error" => "Data not found"], 404);
        }
        // DB::table('special_days')->update(['view' => false]);
        $updated = $specialDay->update(['view' => $specialDay->first()->view == true ? false : true]);
        return response()->json(["updated" => $updated], 202);
    }

    public function deleteSpcDay(Request $req)
    {
        $specialDay = SpecialDay::where('id', $req->special_day_id);
        if (! $specialDay->first()) {
            return response()->json(["error" => "Data not found"], 404);
        }
        $deleted = $specialDay->delete();
        return response()->json(["deleted" => $deleted], 202);
    }

    public function addEventSpcDay(Request $req)
    {
        // add event in seleccted special day
        $validator = Validator::make($req->all(), [
            'event_id'       => 'required|array',
            'special_day_id' => "required|string",
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 403);
        }
        $specialDay = SpecialDay::where('id', $req->special_day_id)->first();
        if (! $specialDay) {
            return response()->json(["error" => "Data not found"], 404);
        }
        $data = [];
        foreach ($req->event_id as $eventId) {
            $event = Event::where('id', $eventId)->where('is_publish', 2)->where('visibility', true)->where('deleted', 0)->where('end_date', '>=', new DateTime('now', new DateTimeZone('Asia/Jakarta')))->first();
            if (! $event) {
                return response()->json(["error" => "Event data not found"], 404);
            }
            if (SpecialDayEvents::where('special_day_id', $req->special_day_id)->where('event_id', $eventId)->first()) {
                return response()->json(["error" => "Event data in one special day group can't duplicated"], 403);
            }
            $countEventSpc = count(SpecialDayEvents::where('special_day_id', $req->special_day_id)->get());
            $data          = SpecialDayEvents::create([
                'special_day_id' => $req->special_day_id,
                'event_id'       => $eventId,
                'priority'       => $countEventSpc + 1,
            ]);
        }
        return response()->json(["data" => $data], 201);
    }

    public function deleteEventSpcDay(Request $req)
    {
        // delete event in selected special day
        $spcDayEvent = SpecialDayEvents::where('id', $req->special_day_event_id);
        if (! $spcDayEvent->first()) {
            return response()->json(["error" => "Event data not found"], 404);
        }
        foreach (SpecialDayEvents::where('special_day_id', $spcDayEvent->first()->special_day_id)->where('priority', '>', intval($spcDayEvent->first()->priority)) as $spcDayEvt) {
            SpecialDayEvents::where('id', $spcDayEvt->id)->update(['priority' => intval($spcDayEvt->priority) - 1]);
        }
        $spcDayId = $spcDayEvent->first()->special_day_id;
        $spcDayEvent->delete();
        return response()->json(["special_day_events" => SpecialDayEvents::where('special_day_id', $spcDayId)->orderBy('priority', 'DESC')->with(['event.org', 'event.tickets'])->get()], 202);
    }

    public function addPrioEventSpcDay(Request $req)
    {
        $spcDayEvent = SpecialDayEvents::where('id', $req->special_day_event_id);
        if (! $spcDayEvent->first()) {
            return response()->json(["error" => "Event data not found"], 404);
        }
        $countEventSpc = count(SpecialDayEvents::where('special_day_id', $spcDayEvent->first()->special_day_id)->get());
        if (intval($spcDayEvent->first()->priority) < $countEventSpc) {
            SpecialDayEvents::where('special_day_id', $spcDayEvent->first()->special_day_id)->where('priority', intval($spcDayEvent->first()->priority) + 1)->update([
                "priority" => $spcDayEvent->first()->priority,
            ]);
            $spcDayEvent->update(["priority" => intval($spcDayEvent->first()->priority) + 1]);
        }
        return response()->json(["special_day_events" => SpecialDayEvents::where('special_day_id', $spcDayEvent->first()->special_day_id)->orderBy('priority', 'DESC')->get()], 202);
    }

    public function minPrioEventSpcDay(Request $req)
    {
        $spcDayEvent = SpecialDayEvents::where('id', $req->special_day_event_id);
        if (! $spcDayEvent->first()) {
            return response()->json(["error" => "Event data not found"], 404);
        }
        if (intval($spcDayEvent->first()->priority) > 1) {
            SpecialDayEvents::where('special_day_id', $spcDayEvent->first()->special_day_id)->where('priority', intval($spcDayEvent->first()->priority) - 1)->update([
                "priority" => $spcDayEvent->first()->priority,
            ]);
            $spcDayEvent->update(["priority" => intval($spcDayEvent->first()->priority) - 1]);
        }
        return response()->json(["special_day_events" => SpecialDayEvents::where('special_day_id', $spcDayEvent->first()->special_day_id)->orderBy('priority', 'DESC')->get()], 202);
    }

    private function detailSpcDay($specialDayId)
    {
        $spcDayEvent = null;
        if ($specialDayId) {
            $spcDayEvent = SpecialDay::where('id', $specialDayId)->first();
        } else {
            $spcDayEvent = SpecialDay::where('view', true)->first();
        }
        if (! $spcDayEvent) {
            return ["error" => "Data not found", "status" => 404];
        }
        $events = [];
        foreach ($spcDayEvent->events()->get() as $key => $spcDayEvt) {
            $event = $spcDayEvt->event()->first();
            if ($event->deleted === 0 && $event->is_publish === 2 && $event->visibility == 1 && new DateTime($event->end_date . " " . $event->end_time, new DateTimeZone('Asia/Jakarta')) >= new DateTime('now', new DateTimeZone('Asia/Jakarta'))) {
                $event->id_data        = $spcDayEvt->id;
                $event->available_days = $event->availableDays()->get();
                $event->org            = $event->org()->first();
                $event->org->legality  = $event->org->credibilityData()->first();
                $event->tickets        = $event->tickets()->orderBy('price', 'ASC')->get();
                $events[]              = $event;
            }
        }
        return ["data" => $spcDayEvent, "events" => $events, "status" => 200];
    }

    public function getSpcDay(Request $req)
    {
        // is only return 1 special day data base from view attribute is true
        $data = $this->detailSpcDay($req->special_day_id);
        if ($data["status"] !== 200) {
            return response()->json(["error" => $data["error"]], $data["status"]);
        }
        return response()->json(["special_day" => ["data" => $data["data"], "events" => $data["events"]]], 200);
    }

    public function getActiveSpcDays()
    {
        $spcDays = SpecialDay::where('view', true)->get();
        $data    = [];
        foreach ($spcDays as $spcDay) {
            $data[] = $this->detailSpcDay($spcDay->id);
        }
        return response()->json(["special_days" => $data], 200);
    }

    public function listSpcDays()
    {
        $spcDays = SpecialDay::all();
        $data    = [];
        foreach ($spcDays as $spcDay) {
            $data[] = $this->detailSpcDay($spcDay->id);
        }
        return response()->json(["special_days" => $data], 200);
    }
    // ==============================================================
    public function createSlctEvent(Request $req)
    {
        if (! $req->title) {
            return response()->json(["error" => 'Title field is required'], 403);
        }
        if ($req->view) {
            DB::table('selected_events')->update(["view" => false]);
        }
        $selectedEvent = SelectedEvent::create([
            "title" => $req->title,
            "view"  => $req->view ? true : false,
        ]);
        return response()->json(["selected_event" => $selectedEvent], 201);
    }

    public function updateSlctEvent(Request $req)
    {
        if (! $req->title) {
            return response()->json(["error" => "Title field iis required"], 403);
        }
        $selectedEvennt = SelectedEvent::where('id', $req->selected_event_id);
        if (! $selectedEvennt->first()) {
            return response()->json(["error" => "Data not found"], 404);
        }
        $updated = $selectedEvennt->update([
            "title" => $req->title,
        ]);
        return response()->json(["updated" => $updated], 202);
    }

    public function setViewSlctEvent(Request $req)
    {
        $selectedEvent = SelectedEvent::where('id', $req->selected_event_id);
        if (! $selectedEvent->first()) {
            return response()->json(["error" => "Data not found"], 404);
        }
        // DB::table('selected_events')->update(['view' => false]);
        $updated = $selectedEvent->update(['view' => $selectedEvent->first()->view == true ? false : true]);
        return response()->json(["updated" => $updated], 202);
    }

    public function deleteSlctEvent(Request $req)
    {
        $selectedEvent = SelectedEvent::where('id', $req->selected_event_id);
        if (! $selectedEvent->first()) {
            return response()->json(["error" => "Data not found"], 404);
        }
        $deleted = $selectedEvent->delete();
        return response()->json(["deleted" => $deleted], 202);
    }

    public function addEventSlctEvent(Request $req)
    {
        // add event in seleccted event
        $validator = Validator::make($req->all(), [
            "event_id"          => "required|array",
            "selected_event_id" => "required|string",
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 403);
        }
        $selctedEvent = SelectedEvent::where('id', $req->selected_event_id)->first();
        if (! $selctedEvent) {
            return response()->json(["error" => "Data not found"], 404);
        }
        $data = [];
        foreach ($req->event_id as $eventId) {
            $event = Event::where('id', $eventId)->where('is_publish', 2)->where('visibility', true)->where('deleted', 0)->where('end_date', '>=', new DateTime('now', new DateTimeZone('Asia/Jakarta')))->first();
            if (! $event) {
                return response()->json(["error" => "Event data not found"], 404);
            }
            if ($event->category === "Attraction" || $event->category === "Tour Travel (recurring)" || $event->category === "Daily Activities") {
                return response()->json(["error" => "This menu only for events data type"], 403);
            }
            if (SelectedEventDatas::where("event_id", $eventId)->where("selected_event_id", $req->selected_event_id)->first()) {
                return response()->json(["error" => "Can't duplicated event data on selceted event group"], 403);
            }
            $countEventSlc = count(SelectedEventDatas::where('selected_event_id', $req->selected_event_id)->get());
            $data[]        = SelectedEventDatas::create([
                'selected_event_id' => $req->selected_event_id,
                'event_id'          => $eventId,
                'priority'          => $countEventSlc + 1,
            ]);
        }
        return response()->json(["data" => $data], 201);
    }

    public function deleteEventSlctEvent(Request $req)
    {
        // delete event in selected event
        $slcEventData = SelectedEventDatas::where('id', $req->selected_event_data_id);
        if (! $slcEventData->first()) {
            return response()->json(["error" => "Event data not found"], 404);
        }
        foreach (SelectedEventDatas::where('selected_event_id', $slcEventData->first()->selected_event_id)->where('priority', '>', intval($slcEventData->first()->priority)) as $slcEvtData) {
            SelectedEventDatas::where('id', $slcEvtData->id)->update(['priority' => intval($slcEvtData->priority) - 1]);
        }
        $slcEventId = $slcEventData->first()->selected_event_id;
        $slcEventData->delete();
        return response()->json(["selected_event_datas" => SelectedEventDatas::where('selected_event_id', $slcEventId)->orderBy('priority', 'DESC')->with(['event.org', 'event.tickets'])->get()], 202);
    }

    public function addPrioEventSlctEvent(Request $req)
    {
        $slcEventData = SelectedEventDatas::where('id', $req->selected_event_data_id);
        if (! $slcEventData->first()) {
            return response()->json(["error" => "Event data not found"], 404);
        }
        $countEventSlc = count(SelectedEventDatas::where('selected_event_id', $slcEventData->first()->selected_event_id)->get());
        if (intval($slcEventData->first()->priority) < $countEventSlc) {
            SelectedEventDatas::where('selected_event_id', $slcEventData->first()->selected_event_id)->where('priority', intval($slcEventData->first()->priority) + 1)->update([
                "priority" => $slcEventData->first()->priority,
            ]);
            $slcEventData->update(["priority" => intval($slcEventData->first()->priority) + 1]);
        }
        return response()->json(["selected_event_datas" => SelectedEventDatas::where('selected_event_id', $slcEventData->first()->selected_event_id)->orderBy('priority', 'DESC')->get()], 202);
    }

    public function minPrioEventSlctEvent(Request $req)
    {
        $slcEventData = SelectedEventDatas::where('id', $req->selected_event_data_id);
        if (! $slcEventData->first()) {
            return response()->json(["error" => "Event data not found"], 404);
        }
        if (intval($slcEventData->first()->priority) > 1) {
            SelectedEventDatas::where('selected_event_id', $slcEventData->first()->selected_event_id)->where('priority', intval($slcEventData->first()->priority) - 1)->update([
                "priority" => $slcEventData->first()->priority,
            ]);
            $slcEventData->update(["priority" => intval($slcEventData->first()->priority) - 1]);
        }
        return response()->json(["selected_event_datas" => SelectedEventDatas::where('selected_event_id', $slcEventData->first()->selected_event_id)->orderBy('priority', 'DESC')->get()], 202);
    }

    private function detailSelectedEvent($selectedEventId)
    {
        // is only return 1 selected event data base from view attribute is true
        $slcEvent = null;
        if ($selectedEventId) {
            $slcEvent = SelectedEvent::where('id', $selectedEventId)->first();
        } else {
            $slcEvent = SelectedEvent::where('view', true)->first();
        }
        if (! $slcEvent) {
            return ["error" => "Data not found", "status" => 403];
        }
        $events = [];
        foreach ($slcEvent->events()->get() as $key => $slcDayEvtData) {
            $event = $slcDayEvtData->event()->first();
            if ($event->deleted === 0 && $event->is_publish === 2 && $event->visibility == 1 && new DateTime($event->end_date . " " . $event->end_time, new DateTimeZone('Asia/Jakarta')) >= new DateTime('now', new DateTimeZone('Asia/Jakarta'))) {
                $event->id_data        = $slcDayEvtData->id;
                $event->available_days = $event->availableDays()->get();
                $event->org            = $event->org()->first();
                $event->org->legality  = $event->org->credibilityData()->first();
                $event->tickets        = $event->tickets()->orderBy('price', 'ASC')->get();
                $events[]              = $event;
            }
        }
        return ["data" => $slcEvent, "events" => $events, "status" => 200];
    }

    public function getSlctEvent(Request $req)
    {
        // is only return 1 selected event data base from view attribute is true
        $data = $this->detailSelectedEvent($req->selected_event_id);
        if ($data["status"] !== 200) {
            return response()->json(["error" => $data["error"]], $data["status"]);
        }
        return response()->json(["selected_event" => ["data" => $data["data"], "events" => $data["events"]]], $data["status"]);
    }

    public function getActiveSlctEvents()
    {
        $slcEvents = SelectedEvent::where('view', true)->get();
        $data      = [];
        foreach ($slcEvents as $slcEvent) {
            $data[] = $this->detailSelectedEvent($slcEvent->id);
        }
        return response()->json(["selected_events" => $data], 200);
    }

    public function listSlctEvents()
    {
        $slcEvents = SelectedEvent::all();
        $data      = [];
        foreach ($slcEvents as $slcEvent) {
            $data[] = $this->detailSelectedEvent($slcEvent->id);
        }
        return response()->json(["selected_events" => $data], 200);
    }
    // ==============================================================
    public function createSlctActivity(Request $req)
    {
        if (! $req->title) {
            return response()->json(["error" => 'Title field is required'], 403);
        }
        if ($req->view) {
            DB::table('selected_activities')->update(["view" => false]);
        }
        $selectedActivity = SelectedActivity::create([
            "title" => $req->title,
            "view"  => $req->view ? true : false,
        ]);
        return response()->json(["selected_activity" => $selectedActivity], 201);
    }

    public function updateSlctActivity(Request $req)
    {
        if (! $req->title) {
            return response()->json(["error" => "Title field iis required"], 403);
        }
        $selectedActivty = SelectedActivity::where('id', $req->selected_activity_id);
        if (! $selectedActivty->first()) {
            return response()->json(["error" => "Data not found"], 404);
        }
        $updated = $selectedActivty->update([
            "title" => $req->title,
        ]);
        return response()->json(["updated" => $updated], 202);
    }

    public function setViewSlctActivity(Request $req)
    {
        $selectedActivity = SelectedActivity::where('id', $req->selected_activity_id);
        if (! $selectedActivity->first()) {
            return response()->json(["error" => "Data not found"], 404);
        }
        // DB::table('selected_activities')->update(['view' => false]);
        $updated = $selectedActivity->update(['view' => $selectedActivity->first()->view == true ? false : true]);
        return response()->json(["updated" => $updated], 202);
    }

    public function deleteSlctActivity(Request $req)
    {
        $selectedActivity = SelectedActivity::where('id', $req->selected_activity_id);
        if (! $selectedActivity->first()) {
            return response()->json(["error" => "Data not found"], 404);
        }
        $deleted = $selectedActivity->delete();
        return response()->json(["deleted" => $deleted], 202);
    }

    public function addEventSlctActivity(Request $req)
    {
        // add event in seleccted event
        $validator = Validator::make($req->all(), [
            "event_id"             => "required|array",
            "selected_activity_id" => "required|string",
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 403);
        }
        $selctedActivity = SelectedActivity::where('id', $req->selected_activity_id)->first();
        if (! $selctedActivity) {
            return response()->json(["error" => "Data not found"], 404);
        }
        $data = [];
        foreach ($req->event_id as $eventId) {
            $event = Event::where('id', $eventId)->where('is_publish', 2)->where('visibility', true)->where('deleted', 0)->where('end_date', '>=', new DateTime('now', new DateTimeZone('Asia/Jakarta')))->first();
            if (! $event) {
                return response()->json(["error" => "Event data not found"], 404);
            }
            if ($event->category !== "Attraction" && $event->category !== "Tour Travel (recurring)" && $event->category !== "Daily Activities") {
                return response()->json(["error" => "This menu only for activities data type"], 403);
            }
            if (SelectedActivityDatas::where("event_id", $eventId)->where("selected_activity_id", $req->selected_activity_id)->first()) {
                return response()->json(["error" => "Can't duplicated event data on selceted event group"], 403);
            }
            $countEventSlc = count(SelectedActivityDatas::where('selected_activity_id', $req->selected_activity_id)->get());
            $data[]        = SelectedActivityDatas::create([
                'selected_activity_id' => $req->selected_activity_id,
                'event_id'             => $eventId,
                'priority'             => $countEventSlc + 1,
            ]);
        }
        return response()->json(["data" => $data], 201);
    }

    public function deleteEventSlctActivity(Request $req)
    {
        // delete event in selected event
        $slcActivityData = SelectedActivityDatas::where('id', $req->selected_activity_data_id);
        if (! $slcActivityData->first()) {
            return response()->json(["error" => "Event data not found"], 404);
        }
        foreach (SelectedActivityDatas::where('selected_activity_id', $slcActivityData->first()->selected_activity_id)->where('priority', '>', intval($slcActivityData->first()->priority)) as $slcActData) {
            SelectedActivityDatas::where('id', $slcActData->id)->update(['priority' => intval($slcActData->priority) - 1]);
        }
        $slcActDataId = $slcActivityData->first()->selected_activity_id;
        $slcActivityData->delete();
        return response()->json(["selected_activity_datas" => SelectedActivityDatas::where('selected_activity_id', $slcActDataId)->orderBy('priority', 'DESC')->with(['event.org', 'event.tickets'])->get()], 202);
    }

    public function addPrioEventSlctActivity(Request $req)
    {
        $slcActivityData = SelectedActivityDatas::where('id', $req->selected_activity_data_id);
        if (! $slcActivityData->first()) {
            return response()->json(["error" => "Event data not found"], 404);
        }
        $countSlcActivity = count(SelectedActivityDatas::where('selected_activity_id', $slcActivityData->first()->selected_activity_id)->get());
        if (intval($slcActivityData->first()->priority) < $countSlcActivity) {
            SelectedActivityDatas::where('selected_activity_id', $slcActivityData->first()->selected_activity_id)->where('priority', intval($slcActivityData->first()->priority) + 1)->update([
                "priority" => $slcActivityData->first()->priority,
            ]);
            $slcActivityData->update(["priority" => intval($slcActivityData->first()->priority) + 1]);
        }
        return response()->json(["selected_activity_datas" => SelectedActivityDatas::where('selected_activity_id', $slcActivityData->first()->selected_activity_id)->orderBy('priority', 'DESC')->get()], 202);
    }

    public function minPrioEventSlctActivity(Request $req)
    {
        $slcActivityData = SelectedActivityDatas::where('id', $req->selected_activity_data_id);
        if (! $slcActivityData->first()) {
            return response()->json(["error" => "Event data not found"], 404);
        }
        if (intval($slcActivityData->first()->priority) > 1) {
            SelectedActivityDatas::where('selected_activity_id', $slcActivityData->first()->selected_activity_id)->where('priority', intval($slcActivityData->first()->priority) - 1)->update([
                "priority" => $slcActivityData->first()->priority,
            ]);
            $slcActivityData->update(["priority" => intval($slcActivityData->first()->priority) - 1]);
        }
        return response()->json(["selected_activity_datas" => SelectedActivityDatas::where('selected_activity_id', $slcActivityData->first()->selected_activity_id)->orderBy('priority', 'DESC')->get()], 202);
    }

    private function detailSelectedActivity($selectedActivityId)
    {
        // is only return 1 selected event data base from view attribute is true
        $slcActivity = null;
        if ($selectedActivityId) {
            $slcActivity = SelectedActivity::where('id', $selectedActivityId)->first();
        } else {
            $slcActivity = SelectedActivity::where('view', true)->first();
        }
        if (! $slcActivity) {
            return ["error" => "Data not found", "status" => 403];
        }
        $events = [];
        foreach ($slcActivity->events()->get() as $key => $slcActivityEventData) {
            $event = $slcActivityEventData->event()->first();
            if ($event->deleted === 0 && $event->is_publish === 2 && $event->visibility == 1 && new DateTime($event->end_date . " " . $event->end_time, new DateTimeZone('Asia/Jakarta')) >= new DateTime('now', new DateTimeZone('Asia/Jakarta'))) {
                $event->id_data        = $slcActivityEventData->id;
                $event->available_days = $event->availableDays()->get();
                $event->org            = $event->org()->first();
                $event->org->legality  = $event->org->credibilityData()->first();
                $event->tickets        = $event->tickets()->orderBy('price', 'ASC')->get();
                $events[]              = $event;
            }
        }
        return ["data" => $slcActivity, "events" => $events, "status" => 200];
    }

    public function getSlctActivity(Request $req)
    {
        // is only return 1 selected event data base from view attribute is true
        $data = $this->detailSelectedActivity($req->selected_activity_id);
        if ($data["status"] !== 200) {
            return response()->json(["error" => $data["error"]], $data["status"]);
        }
        return response()->json(["selected_activity" => ["data" => $data["data"], "events" => $data["events"]]], $data["status"]);
    }

    public function getActiveSlctActivities()
    {
        $slcActivites = SelectedActivity::where('view', true)->get();
        $data         = [];
        foreach ($slcActivites as $slcActivity) {
            $data[] = $this->detailSelectedActivity($slcActivity->id);
        }
        return response()->json(["selected_activities" => $data], 200);
    }

    public function listSlctActivities()
    {
        $slcActivites = SelectedActivity::all();
        $data         = [];
        foreach ($slcActivites as $slcActivity) {
            $data[] = $this->detailSelectedActivity($slcActivity->id);
        }
        return response()->json(["selected_activities" => $data], 200);
    }
    // ==============================================================
    public function setViralCity(Request $req)
    {
        if (! $req->city_id) {
            return response()->json(["error" => "City ID field is required"], 403);
        }
        $city = City::where('id', $req->city_id)->first();
        if (! $city) {
            return response()->json(["error" => "City data not found"], 404);
        }
        DB::table('viral_cities')->delete();
        ViralCity::create(['city_id' => $req->city_id]);
        return response()->json(["message" => $city->name . " have set to VIRAL filter"], 202);
    }
    // ==============================================================
    public function createAdmin(Request $req)
    {
        $validator = Validator::make($req->all(), [
            "user_id" => "required|string",
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 403);
        }
        $user = User::where('id', $req->user_id)->first();
        if (! $user) {
            return response()->json(["error" => "Data not found"], 404);
        }
        if ($user->admin()->first()) {
            return response()->json(["error" => "User statuses is already an admin"], 403);
        }
        $admin = Admin::create([
            "user_id" => $req->user_id,
        ]);
        User::where('id', $req->user_id)->update(["is_active" => "1"]);
        $admin->user = $user;
        return response()->json(["data" => $admin], 201);
    }

    public function deleteAdmin(Request $req)
    {
        $validator = Validator::make($req->all(), [
            "admin_id" => "required|string",
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 403);
        }
        $admin     = Admin::where('id', $req->admin_id);
        $adminData = $admin->first();
        if (! $adminData) {
            return response()->json(["error" => "Admin data not found"], 404);
        }
        if (Auth::user()->id == $adminData->user_id) {
            return response()->json(["error" => "You can't remove admin status from your own account"], 403);
        }
        $deleted = $admin->delete();
        return response()->json(["deleted" => $deleted], 202);
    }

    public function admins(Request $req)
    {
        $admins = Admin::all();
        foreach ($admins as $admin) {
            $admin->user = $admin->user()->first();
        }
        return response()->json(["admins" => $admins], 200);
    }
    // ===============================================================
    public function deletePch(Request $req)
    {
        $validator = Validator::make($req->all(), [
            "pch_id" => "required|string",
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 403);
        }
        $purchase = Purchase::where('id', $req->pch_id);
        $pchData  = $purchase->first();
        if (! $pchData) {
            return response()->json(["error" => "Purchase data not found"], 404);
        }
        if ($pchData->amount == 0) {
            return response()->json(["error" => "This transaction status is succeeded. You can't remove this transaction"], 403);
        }
        $deleted = $purchase->delete();
        return response()->json(["deleted" => $deleted], 202);
    }

    public function pchDetail(Request $req)
    {
        $purchase = Purchase::where('id', $req->pch_id)->first();
        if (! $purchase) {
            $purchase = Purchase::where('pay_id', $req->pay_id)->first();
            if (! $purchase) {
                return response()->json(["error" => "Purchase data not found"], 404);
            }
        }
        $purchase->user    = $purchase->user()->first();
        $purchase->payment = $purchase->payment()->first();
        $purchase->event   = $purchase->ticket()->first()->event()->first();
        $purchase->ticket  = $purchase->ticket()->first();
        return response()->json(["purchase" => $purchase], 200);
    }

    public function purchases(Request $req)
    {
        $purchases = Purchase::all();
        foreach ($purchases as $purchase) {
            $purchase->user    = $purchase->user()->first();
            $purchase->payment = $purchase->payment()->first();
            $purchase->event   = $purchase->ticket()->first()->event()->first();
            $purchase->ticket  = $purchase->ticket()->first();
        }
        return response()->json(["purchases" => $purchases], 200);
    }
    // ===============================================================
    public function deletePayment(Request $req)
    {
        $payment = Payment::where('id', $req->pay_id);
        $payData = $payment->first();
        if (! $payData) {
            return response()->json(["error" => "Payment data not found"], 404);
        }
        if ($payData->pay_state == "SUCCEEDED") {
            return response()->json(["error" => "This transaction status is succeeded. You can't remove this transaction"], 403);
        }
        foreach ($payData->purchases()->get as $purchase) {
            if ($purchase->amount == 0) {
                return response()->json(["error" => "This transaction status is succeeded. You can't remove this transaction"], 403);
            }
        }
        $deleted = $payment->delete();
        return response()->json(["delted" => $deleted], 202);
    }

    public function paymentDetail(Request $req)
    {
        $payment = Payment::where('id', $req->pay_id)->first();
        if (! $payment) {
            return response()->json(["error" => "Payment data not found"], 404);
        }
        $payment->user = $payment->user()->first();
        return response()->json(["payment" => $payment], 200);
    }

    public function payments(Request $req)
    {
        $payemnts = Payment::all();
        foreach ($payemnts as $payment) {
            $payment->user = $payment->user()->first();
        }
        return response()->json(["payments" => $payemnts], 200);
    }

    private function countPurchases($event)
    {
        $total = 0;
        foreach ($event->tickets as $ticket) {
            $total += count($ticket->purchases);
        }
        return $total;
    }

    // =============================================================================================

    public function udpdateProfitSetting(Request $req)
    {
        $validator = Validator::make($req->all(), [
            'ticket_commision' => 'required|numeric',
            'admin_fee_trx'    => 'required|numeric',
            'admin_fee_wd'     => 'required|numeric',
            'mul_pay_gate_fee' => 'required|numeric',
            'tax_fee'          => 'required|numeric',
            'wa_inv_price'     => 'required|numeric',
        ]);
        if ($validator->fails()) {
            return response()->json(["error" => $validator->errors()], 403);
        }
        ProfitSetting::where('id', 1)->update([
            'ticket_commision'   => $req->ticket_commision,
            'admin_fee_trx'      => $req->admin_fee_trx,
            'admin_fee_wd'       => $req->admin_fee_wd,
            'mul_pay_gate_fee'   => $req->mul_pay_gate_fee,
            'tax_fee'            => $req->tax_fee,
            'wa_quota_price_inv' => $req->wa_inv_price,
        ]);
        return response()->json(["profit_setting" => ProfitSetting::first()], 200);
    }

    public function getProfitSetting()
    {
        return response()->json(["profit_setting" => ProfitSetting::first(), "pg_config" => config('payconfigs')], 200);
    }

    // ===============================================================================================

    public function createRefundSetting(Request $req)
    {
        $validator = Validator::make($req->all(), [
            'day_before'   => "required|numeric",
            'allow_refund' => "required|numeric",
        ]);
        if ($validator->fails()) {
            return response()->json(["error" => $validator->errors()], 403);
        }
        if ($req->day_before == -1 && RefundSetting::where('day_before', -1)->first()) {
            return response()->json(["eeror" => "There can only be one refund rule for event cancellation"], 403);
        }
        $refundSetting = RefundSetting::create([
            'day_before'   => $req->day_before,
            'allow_refund' => $req->allow_refund,
        ]);
        return response()->json(["refund_setting" => $refundSetting], 201);
    }

    public function updateRefundSetting(Request $req)
    {
        $validator = Validator::make($req->all(), [
            'day_before'   => "required|numeric",
            'allow_refund' => "required|numeric",
        ]);
        if ($validator->fails()) {
            return response()->json(["error" => $validator->errors()], 403);
        }
        $obj = RefundSetting::where('id', $req->id);
        if (! $obj->first()) {
            return response()->json(["error" => "Data not found"], 404);
        }
        $obj->update([
            'day_before'   => $req->day_before,
            'allow_refund' => $req->allow_refund,
        ]);
        return response()->json(["refund_setting" => $obj->first()], 202);
    }

    public function deleteRefundSetting(Request $req)
    {
        $obj = RefundSetting::where('id', $req->id);
        if (! $obj->first()) {
            return response()->json(["error" => "Data not found"], 404);
        }
        $obj->delete();
        return response()->json(["message" => "Data deleted successfully"], 202);
    }

    public function refundSettings()
    {
        return response()->json(["refund_settings" => RefundSetting::orderBy('day_before', 'DESC')->get()], 200);
    }

    public function activateInvFeature(Request $req)
    {
        $validateData = [
            'org_id' => 'required|string',
        ];
        if (isset($req->enable) && $req->enable == true) {
            $validateData["inv_quota"] = "required|numeric";
        }
        $validator = Validator::make($req->all(), $validateData);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 403);
        }
        $org = Organization::where('id', $req->org_id)->first();
        if (! $org) {
            return response()->json(["error" => "Organization data not found"], 404);
        }
        $setting  = ProfitSetting::first();
        $totalPay = (intval($setting->wa_quota_price_inv) * intval($req->inv_quota)) + (intval($setting->wa_quota_price_ntf) * intval($req->ntf_quota));
        $updated  = Organization::where('id', $req->org_id)->update([
            'allow_create_inv' => isset($req->enable) && $req->enable == true,
            'create_inv_quota' => isset($req->enable) && $req->enable == true ? $req->inv_quota : $org->create_inv_quota,
        ]);
        return response()->json(["meesage" => $updated . " data was updated", "total" => $totalPay], $updated === 0 ? 404 : 202);
    }

    // ================================================================================================

    public function events(Request $req)
    {
        $events    = Event::where('is_publish', '<', 3)->with(['org', 'availableDays', 'tickets', 'org.credibilityData', 'tickets.purchases'])->get();
        $fixEvents = [];
        for ($i = 0; $i < count($events); $i++) {
            $selectedIndex = $i;
            $selectedValue = $this->countPurchases($events[$selectedIndex]);
            for ($j = $i + 1; $j < count($events); $j++) {
                $toCompare = $this->countPurchases($events[$j]);
                if ($selectedValue < $toCompare) {
                    $selectedValue = $toCompare;
                    $selectedIndex = $j;
                }
            }
            if ($selectedIndex != $i) {
                $tmp                    = $events[$i];
                $events[$i]             = $events[$selectedIndex];
                $events[$selectedIndex] = $tmp;
                $tmp                    = null;
            }

            array_push($fixEvents, $events[$i]);
        }
        return response()->json(["events" => $fixEvents], 200);
    }

}
