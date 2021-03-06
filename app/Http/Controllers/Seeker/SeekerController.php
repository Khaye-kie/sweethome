<?php

namespace App\Http\Controllers\Seeker;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Property;
use App\Models\FavoriteProperty;
use Illuminate\Support\Facades\Auth;
use App\Rules\MatchOldPassword;
use App\Models\Appointment;
use App\Models\ChMessage as Message;
use App\Models\PropertyMorePhotos;
use App\Models\Rating;
use LaravelDaily\LaravelCharts\Classes\LaravelChart;
use Illuminate\Support\Facades\Hash;

class SeekerController extends Controller
{
    protected $user_id;

    public function index()
    {
       
        $agents = User::latest()
            ->where('role_id', 1)
            ->where('status', 1)
            ->take(3)
            ->get();
        $listings = Property::all();
        $listings = Property::latest()
            
            ->where('status', 1)
            ->paginate(3);
        $msg = Message::all()
            ->where('seen', 0)
            ->where('from_id', '!=', Auth::id())
            ->where('to_id', Auth::id());

        

        return view('dashboards.seeker.index', compact('listings', 'agents', 'msg'));
    }

    public function viewProperty($id)
    {
        
        $listings = Property::where('id', $id)->first();
        $agents = User::where('agent_id');
        $msg = Message::all()
            ->where('seen', 0)
            ->where('from_id', '!=', Auth::id())
            ->where('to_id', Auth::id());
        $images = PropertyMorePhotos::all()->where('property_id', $id);


        return view('dashboards.seeker.property-view', compact('listings', 'agents', 'msg', 'images'));
    }

    public function allProperties(Request $request)
    {
        $address = $request->address;
        $category = $request->category; 
        $type = $request->type;
        $minprice = $request->minprice;
        $maxprice = $request->maxprice; 
        $listings = Property::where('status', 1)
            ->when($address, function ($query, $address) {
                return $query->where('street_brgy', 'LIKE', '%' .$address. '%')->orWhere('city', 'LIKE', '%' .$address. '%')->orWhere('province', 'LIKE', '%' .$address. '%');
            })->when($category, function ($query, $category) {
                return $query->where('category', '=', $category);
            })
            ->when($type, function ($query, $type) {
                return $query->where('type', '=', $type);
            })
            ->when($minprice, function ($query, $minprice) {
                return $query->where('price', '>=', $minprice);
            })
            ->when($maxprice, function ($query, $maxprice) {
                return $query->where('price', '<=', $maxprice);
            })
            ->get();
        $msg = Message::all()
            ->where('seen', 0)
            ->where('from_id', '!=', Auth::id())
            ->where('to_id', Auth::id());

        return view('dashboards.seeker.all-properties', compact('listings', 'msg'));
    }

    public function allAgents(Request $request)
    {
        $search = $request->agent;
        $agents = User::where('role_id', 1)
            ->when($search, function ($query, $search) {
                return $query->where('given_name', 'LIKE', '%' .$search. '%')->orWhere('last_name', 'LIKE', '%' .$search. '%')->orWhere('username', 'LIKE', '%' .$search. '%');
            })
            ->where('status', 1)
            ->get();
        
        $msg = Message::all()
            ->where('seen', 0)
            ->where('from_id', '!=', Auth::id())
            ->where('to_id', Auth::id());

        
        $agentRatings = [
            'name' => 'Agent Total Sales',
            'chart_title' => 'Total Sales ???',
            'chart_type' => 'bar',
            'report_type' => 'group_by_relationship',
            'model' => 'App\Models\Property',
        
            'relationship_name' => 'user', 
            'group_by_field' => 'username',
            'group_by_period' => 'month',
            'where_raw' => 'status = 0 AND type = "sale"',
        
            'aggregate_function' => 'sum',
            'aggregate_field' => 'price',

            'chart_color' => '249, 166, 2',
            'top_results' => 10,

            
            
        ];

        // $agentRatings = [
        //     'chart_title' => 'Star Ratings',
        //     'chart_type' => 'bar',
        //     'report_type' => 'group_by_relationship',
        //     'model' => 'App\Models\Rating',
        
        //     'relationship_name' => 'agent', 
        //     'group_by_field' => 'username',
        
        //     'aggregate_function' => 'avg',
        //     'aggregate_field' => 'star_rate',

        //     'chart_color' => '249, 166, 2',
        //     'top_results' => 10
            
            
        // ];
        $agentRatingsChart = new LaravelChart($agentRatings);
    
        return view('dashboards.seeker.all-agents', compact('search', 'agents', 'msg', 'agentRatingsChart'));
    }

    public function aboutUs()
    {
        $msg = Message::all()
            ->where('seen', 0)
            ->where('from_id', '!=', Auth::id())
            ->where('to_id', Auth::id());
        return view('dashboards.seeker.about-us', compact('msg'));
    }

    public function profile()
    {
        $msg = Message::all()
            ->where('seen', 0)
            ->where('from_id', '!=', Auth::id())
            ->where('to_id', Auth::id());
        return view('dashboards.seeker.profile', compact('msg'));
    }

    public function updateProfile(Request $request)
    {
        $request->validate([
            'given_name'    => ['required','string','regex:/^[a-zA-Z\s]+$/u','max:255'],
            'last_name'     => ['required','string','regex:/^[a-zA-Z\s]+$/u','max:255'],
            'city'          => ['required','string','regex:/^[- ,\/0-9a-zA-Z]+/'],
            'province'      => ['required','string','regex:/^[- ,\/0-9a-zA-Z]+/'],
            'phone_no'      => ['required','string','regex:/^(09|\+639)\d{9}$/'],
            'image'         => ['mimes:jpg,png,jpeg','max:5048'],
            'email'         => 'required|email|unique:users,email,'.Auth::user()->id,
            'username'      => 'required|string|min:4|unique:users,username,'.Auth::user()->id,
        ]);

        $input = $request->all();

        if ($image = $request->file('image')) {
            $path = 'storage/images/';
            $new_image_name = date('YmdHis') . "." . $image->getClientOriginalExtension();
            $image->move($path, $new_image_name);
            $input['image'] = "$new_image_name";
        }else{
            unset($input['image']);
        }
        User::find(Auth::user()->id)->update($input);

        $msg = Message::all()
        ->where('seen', 0)
        ->where('from_id', '!=', Auth::id())
        ->where('to_id', Auth::id());

        return redirect()->route('seeker.profile')->withMessage('Your profile info has been updated successfuly.');
    }

    public function changePassword()
    {
        $msg = Message::all()
            ->where('seen', 0)
            ->where('from_id', '!=', Auth::id())
            ->where('to_id', Auth::id());
        return view('dashboards.seeker.change-password', compact('msg'));
    }

    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password'      => ['required', new MatchOldPassword],
            'new_password'          => ['required', 'regex:/^(?=.*[A-Z])(?=.*[a-z])(?=.*[0-9]).{8,30}$/','min:8', 'max:30'],
            'confirm_new_password'  => ['same:new_password'],
        ],[
            'new_password.regex' => 'Password must have at least one of each type among lowercase, uppercase, and numbers between 8 and 30 characters.'
        ]);
        
        User::find(auth()->user()->id)->update(['password'=> Hash::make($request->new_password)]);
   
        return redirect()->route('seeker.change-password')->withMessage('Your password has been changed successfully. Please try to re-login with updated password.');
    }

    public function viewPropertyWithBtnRemoveToFavorites($id)
    {
        $favorite = FavoriteProperty::findOrFail($id);
        $msg = Message::all()
            ->where('seen', 0)
            ->where('from_id', '!=', Auth::id())
            ->where('to_id', Auth::id());
        $images = PropertyMorePhotos::all()->where('property_id', $id);
        

        return view('dashboards.seeker.favorite-with-remove-btn', compact('favorite', 'msg', 'images'));
    }

    public function agentProfile($id)
    {
        $agents = User::where('role_id', 1)
            ->where('id', $id)
            ->first();
        $listings = Property::orderBy('created_at', 'desc')
            ->where('agent_id', $id)
            ->paginate(3, ['*'], 'listings');
        $msg = Message::all()
            ->where('seen', 0)
            ->where('from_id', '!=', Auth::id())
            ->where('to_id', Auth::id());
        $ratings = Rating::where('user_id', Auth::id())
            ->where('agent_id', $id)
            ->first();
        $count_ratings = Rating::where('agent_id', $id)
            ->orderBy('id', 'DESC')
            ->paginate(3, ['*'], 'ratings');
        $sum_of_ratings = Rating::where('agent_id', $id)
            ->sum('star_rate');
        if($count_ratings->count() > 0)
        {
            $average_of_ratings = $sum_of_ratings/$count_ratings->total();
        }
        else
        {
            $average_of_ratings = 0;
        }
        return view('dashboards.seeker.visit-agent-profile', 
            compact('agents', 'listings', 'msg', 'ratings', 'average_of_ratings', 'count_ratings'));
    }
    
}
