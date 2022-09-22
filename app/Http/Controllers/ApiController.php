<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Customer;
use App\Models\Category;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Twilio\Rest\Client;

class ApiController extends Controller
{
    //
    public function __construct()
    {
        $this->serverUrl = "http://192.168.111.120:8000/";
    }

     public function testSMS (Request $request){
        $this->sendSMS('User registration successful!!', $request->phone_number);
    }

    public function testfriend(Request $request){
        $customer_name = DB::select("SELECT c_name FROM snap_customers WHERE c_id =".$request->pro_customer_id.";");
        // return $customer_name;
        if(count($customer_name))
        return $this->sendnotitofriend($request->pro_customer_id, 'New post', $request->pro_name." is posted by ".$customer_name[0]->c_name);        
    }

    private function sendSMS($message, $recipients)
{
    $account_sid = $_ENV["TWILIO_SID"];
    $auth_token = $_ENV["TWILIO_AUTH_TOKEN"];
    $twilio_number = $_ENV["TWILIO_NUMBER"];

    $client = new Client($account_sid, $auth_token);

    @$client->messages->create($recipients, 
            ['from' => $twilio_number, 'body' => $message] );
}

   
    public function signup(Request $request) {
        $users = Customer::where('c_phone_number', $request->c_phone_number)
                            ->orWhere('c_email', $request->c_email)
                            ->orWhere('c_name', $request->c_name)
                            ->get();
        if(count($users) && isset($users[0]->c_phone_verified)) {
            return array(
                "flag" => 0,
                "msg" => 'Email Or Phone Number Or UserName is already used',
                "data" => ''
            );
        } else {
            $verify_code = rand(1999, 9999);
            if(count($users)){
                $user = Customer::where('c_phone_number', $request->c_phone_number)
                            ->update(['c_verify_code' => $verify_code, 'c_email' => $request->c_email]);
            } else {
                $user = Customer::create([
                    'c_name' => $request->c_name,
                    'c_email' => $request->c_email,
                    'c_phone_number' => $request->c_phone_number,
                    'c_password' => Hash::make($request->c_pwd),
                    'c_verify_code' => $verify_code
                ]);
                // $this->sendSMS('Your verification code is '.$verify_code, $request->c_phone_number);
            }

            $this->send_sms($request->c_phone_number, $verify_code);
            
            return array(
                "flag" => 1,
                "msg" => 'You signup successfully',
                "data" => $user
            );
        }
        
        return $users;
    }

    public function resendcode(Request $request){
        $user = Customer::where('c_phone_number', $request->c_phone_number)
                                ->get();
        if(count($user)){
            $verify_code = rand(1999, 9999);
            $update = Customer::where('c_phone_number', $request->c_phone_number)
                            ->update(['c_verify_code' => $verify_code]);

            // $this->sendSMS('Your verification code is '.$verify_code, $request->c_phone_number);

            return array(
                "flag" => 1,
                "msg" => 'We sent verify code to '.$request->c_phone_number,
                "data" => $user
            );
        } else {
            return array(
                "flag" => 0,
                "msg" => 'Phone number is not registered',
                "data" => ''
            );
        }
    }

    public function verify(Request $request){
        $user = Customer::where('c_phone_number', $request->c_phone_number)
                        ->where('c_verify_code', $request->c_verify_code)
                                ->get();
        if(count($user)){
            Customer::where('c_phone_number', $request->c_phone_number)
                        ->update(['c_phone_verified'=> 1]);
            $user = Customer::where('c_phone_number', $request->c_phone_number)
                                ->get();
            $user[0]->avatar = $user[0]->c_avatar? $this->serverUrl."uploads/customers/".$user[0]->c_avatar : '';
            return array(
                "flag" => 1,
                "msg" => 'Phone Number is verified',
                "data" => $user
            );
        } else {
            return array(
                "flag" => 0,
                "msg" => 'Invalide Verify code',
                "data" => ''
            );
        }
    }

    public function login(Request $request){
        $user = Customer::where('c_email', $request->c_email)
                        ->where('c_phone_verified', 1)
                        ->get();
        if(count($user)){
            if(Hash::check($request->c_pwd, $user[0]->c_password)){
                $user[0]->avatar = $user[0]->c_avatar? $this->serverUrl."uploads/customers/".$user[0]->c_avatar : '';
                return array(
                    "flag" => 1,
                    "msg" => 'Success',
                    "data" => $user[0]
                );
            } else {
                return array(
                    "flag" => 0,
                    "msg" => 'Emaiil or Password is not correct',
                    "data" => ''
                );
            }
        } else {
            return array(
                "flag" => 0,
                "msg" => 'Email is not registered',
                "data" => $request->c_email
            );
        }
    }

    public function getcategories(Request $request){
        $category = Category::all();
        $temp = array();
        foreach($category as $cat){
            $cat->cat_image = $this->serverUrl."uploads/categories/".$cat->cat_image;
            $temp[] = $cat;
        }
        return $temp;
    }

    public function getsubcategory(Request $request){
        $cat_id = $request->cat_id;
        $RTL = $request->RTL;
        if($RTL){
            $query = "SELECT sCat_id AS `value`, sCat_name_en AS label FROM snap_category_sub WHERE sCat_parent_cat = ".$cat_id." ORDER BY sCat_name_en";
        } else {
            $query = "SELECT sCat_id AS `value`, sCat_name_ar AS label FROM snap_category_sub WHERE sCat_parent_cat = ".$cat_id." ORDER BY sCat_name_ar";
        }
        $result = DB::select($query);

        return array(
            "data" => $result
        );
    }

    public function filterproducts(Request $request){

        $category_id = $request->category_id;
        // $sub_categories = $request->subCategories ? $request->subCategories : array();
        // $locations = $request->locations ? $request->locations : array();

        $sub_categories = array();
        $locations = array();

        $RTL = $request->RTL;

        $res_sub_categories = array();
        if(!$category_id){
            return [];            
        }
        
        $get_subCat_query = '';
        
        if(!count($sub_categories)){
            $get_subCat_query = "SELECT * FROM snap_category_sub WHERE sCat_parent_cat = ".$category_id.";";

            $sub_categories_result = DB::select($get_subCat_query);
            $sub_categories = array();

            foreach($sub_categories_result as $sub_result){
                $sub_categories[] = $sub_result->sCat_id;
                $res_sub_categories[] = array(
                    "value" => $sub_result->sCat_id,
                    "label" => $RTL ? $sub_result->sCat_name_ar : $sub_result->sCat_name_en
                );
            }
        }

        $query_category = "";
        
        for($i = 0; $i < count($sub_categories); $i++){

            $query_category .= "cat_id = ".$sub_categories[$i]." ";
            
            if(($i + 1) < count($sub_categories)){
                $query_category .= "OR ";
            }

        }

        if($query_category){
            $query = "SELECT * FROM (SELECT *, COUNT(pro_id) AS num FROM snap_product_cat WHERE ".$query_category." GROUP BY pro_id) t1 WHERE t1.num >=0";    
        } else {
            return array(
                "sub_categories" => $res_sub_categories,

                "categori_id" => gettype($category_id),
                "locations" => $locations,
                "sub" => $get_subCat_query,
                "data" => array()
            );
        }
        

        $results = DB::select($query);

        $products = array();
        foreach ($results as $pro) {

            $pro_data = DB::select("SELECT * FROM snap_products WHERE pro_id = ".$pro->pro_id);
            $pro_category = DB::select("SELECT * FROM snap_product_cat WHERE pro_id = ".$pro->pro_id);
            
            $temp = array();

            foreach($pro_category as $p_cat){
                $category = DB::select("SELECT * FROM snap_category_sub WHERE sCat_id = ".$p_cat->cat_id);
                // $category = Category::where('cat_id', $p_cat->cat_id)->get();

                if(count($category)){
                    $temp[] = array(
                        "cat_id" => $category[0]->sCat_id,
                        "cat_name_en" => $category[0]->sCat_name_en,
                        "cat_name_ar" => $category[0]->sCat_name_ar
                    );
                }
            }

            $pro_reviews = DB::select("SELECT COUNT(*) AS num, AVG(rev_point) AS points FROM snap_reviews WHERE rev_to_proid = ".$pro->pro_id);

            if(count($pro_data))
            $products[] = array(
                "pro_id"=> $pro_data[0]->pro_id,
                "pro_name"=> $pro_data[0]->pro_name,
                "pro_description"=> $pro_data[0]->pro_description,
                "pro_location"=> $pro_data[0]->pro_location,
                "pro_sub_location"=> $pro_data[0]->pro_sub_location,
                "pro_location_id" => $pro_data[0]->pro_location_id,
                "pro_price"=> $pro_data[0]->pro_price,
                "pro_currency"=> $pro_data[0]->pro_currency,
                "pro_currency_symbo"=> $pro_data[0]->pro_currency_symbo,
                "pro_media_type"=> $pro_data[0]->pro_media_type,
                "pro_media_url"=> $this->serverUrl."uploads/products/".$pro_data[0]->pro_media_url,
                "pro_promoted"=> $pro_data[0]->pro_promoted,
                "pro_customer_id"=> $pro_data[0]->pro_customer_id,
                "created_at"=> $pro_data[0]->created_at,
                "updated_at"=> $pro_data[0]->updated_at,
                "pro_review_num" => count($pro_reviews) ? $pro_reviews[0]->num : 0,
                "pro_review_point" => count($pro_reviews) ? $pro_reviews[0]->points: 0,
                "pro_categories" => $temp
            );
        }
        $products_results = array();
        foreach($products as $pro){
            $user = DB::select("SELECT * FROM snap_customers WHERE c_id = ".$pro['pro_customer_id']);
            $pro['customer'] = array(
                "customer_avatar"=>$user[0]->c_avatar ? $this->serverUrl."uploads/customers/".$user[0]->c_avatar: '',
                "customer_name"=>$user[0]->c_name,
                "customer_full_name"=>$user[0]->c_full_name
            );
            $products_results[] = $pro;
        }

        return array(
            "sub_categories" => $res_sub_categories,

            "categori_id" => gettype($category_id),
            "locations" => $locations,
            "sub" => $get_subCat_query,
            "data" => $products_results
        );

        return gettype($categori_id);
        // $categories = json_decode($request->categories);
        
        
        
        

        
        
    }

    public function getprofile(Request $request){
        $user = Customer::where("c_id", $request->customer_id)->get();
        if(count($user)){
            $user[0]->avatar = $user[0]->c_avatar? $this->serverUrl."uploads/customers/".$user[0]->c_avatar : '';
            return array(
                "flag" => 1,
                "msg" => '',
                "data" => $user[0]
            );
        } else {
            return array(
                "flag" => 0,
                "msg" => 'This account is closed',
                "data" => ''
            );
        }
        
    }

    public function getreviews(Request $request){
        $reviews = DB::select('SELECT * FROM snap_reviews WHERE rev_to_uid = '.$request->customer_id);
        $result = array();

        foreach($reviews as $rev){
            $product = DB::select('SELECT * FROM snap_products WHERE pro_id = '.$rev->rev_to_proid);
            $reviewer = DB::select('SELECT * FROM snap_customers WHERE c_id = '.$rev->rev_from_uid);

            if(count($product)){
                $product[0]->pro_media_url = $this->serverUrl."uploads/products/".$product[0]->pro_media_url;
                if(count($reviewer)){
                    $reviewer[0]->avatar = $reviewer[0]->c_avatar ? $this->serverUrl."uploads/customers/".$reviewer[0]->c_avatar : "" ;
                    $rev->reviewer = $reviewer[0];
                }
                $rev->product = $product[0];
                
                $result[] = $rev;    
            }
        }

        return $result;
    }

    public function getproducts(Request $request){
        $products = DB::select('SELECT * FROM snap_products WHERE pro_customer_id = '.$request->customer_id);
        
        $result = array();
        foreach($products as $pro){
            $pro->pro_media_url = $this->serverUrl."uploads/products/".$pro->pro_media_url;
            $result[] = $pro;
        }

        return $result;
    }

    public function togglefollow(Request $request){
        $follow = DB::select('SELECT * FROM snap_follow WHERE f_from_uid = '.$request->from_uid.' AND f_to_uid = '.$request->to_uid);
        
        if(count($follow)){
            DB::table('snap_follow')
                ->where('f_to_uid', '=', $request->to_uid)
                ->where('f_from_uid', '=', $request->from_uid)
                ->delete();
            return array(
                'flag' => 1,
                'data' => 0
            );
        } else {
            DB::insert('INSERT into snap_follow (f_from_uid, f_to_uid) values (?, ?)', [$request->from_uid, $request->to_uid]);
            return array(
                'flag' => 1,
                'data' => 1
            );
        }
    }

    public function getfollow(Request $request){
        $follow = DB::select('SELECT * FROM snap_follow WHERE f_from_uid = '.$request->from_uid.' AND f_to_uid = '.$request->to_uid);
        
        if(count($follow)){
            return array(
                'flag' => 1,
                'data' => 1
            );
        } else {
            return array(
                'flag' => 1,
                'data' => 0
            );
        }
    }
    public function addFavoritLocation($c_id, $l_id){
        $favorit = DB::select("SELECT * from snap_favorite_locations WHERE c_id = ".$c_id." AND l_id = ".$l_id);
        if(count($favorit)){

        } else {
            DB::insert('INSERT into snap_favorite_locations (l_id, c_id, visited_times) values (?, ?, ?)', [$l_id, $c_id, 1]);    
        }
    }

    public function sendnotitofriend($customer_id, $title, $body){
        $friends = DB::select('SELECT * FROM snap_follow WHERE f_to_uid = '.$customer_id);
        // return  $this->sendpush($friends[0]->f_from_uid, $title, $body);
        foreach($friends as $friend){
            $this->sendpush($friend->f_from_uid, $title, $body, true);
        }
    }
    
    public function editFile(Request $request) {
        if($request->filechanged){
            $files = $request->file('file_attachment');
            if(!$files)
            {
                return array(
                    "status"=> 0,
                    "msg"=>"Post is Failed",
                    "data"=>'');
            }
            // $testPro = DB::select("SELECT * FROM snap_products WHERE pro_name = ".$request->pro_name);
            // if(count($testPro)){
            //  return array(
            //         "status"=> 0,
            //         "msg"=>"The same product name is already exist",
            //         "data"=>'');   
            // }
            if($request->pro_media_type == 'image'){
                $filename = "product".round(microtime(true) * 1000).".jpg";
            } else {
                $filename = "product".round(microtime(true) * 1000).".mp4";
            }
            request()->file_attachment->move(public_path('uploads/products/'), $filename);

        }
        if(isset($filename)){
            DB::update('update snap_products set pro_name = ?,pro_description=?,pro_location=?,pro_location_id=?, pro_price=?, pro_currency=?, pro_currency_symbo=?, pro_media_type=?, pro_media_url=? where pro_id = ?',[
                $request->pro_name,
                $request->pro_description,
                $request->pro_location,
                $request->pro_location_id, 
                $request->pro_price,
                $request->pro_currency,
                $request->pro_currency_symbol,
                $request->pro_media_type,
                $filename,
                $request->pro_id,
            ]);    
        } else {
            DB::update('update snap_products set pro_name = ?,pro_description=?,pro_location=?,pro_location_id=?, pro_price=?, pro_currency=?, pro_currency_symbo=? where pro_id = ?',[
                $request->pro_name,
                $request->pro_description,
                $request->pro_location,
                $request->pro_location_id, 
                $request->pro_price,
                $request->pro_currency,
                $request->pro_currency_symbol,
                $request->pro_id,
            ]);    
        }

        $customer_name = DB::select("SELECT c_name FROM snap_customers WHERE c_id =".$request->pro_customer_id.";");
        // return $customer_name;
        if(count($customer_name))
        $this->sendnotitofriend($request->pro_customer_id, 'Edit Product', $request->pro_prev_name." is edited by ".$customer_name[0]->c_name);        
        // $this->addFavoritLocation($request->pro_customer_id, $request->pro_location_id);

        DB::delete('delete from snap_product_cat where pro_id = ?',[$request->pro_id]);
        $categories = json_decode($request->pro_subcategories);
        foreach($categories as $category){
            DB::table('snap_product_cat')->insertGetId([
                "cat_id"=>$category,
                "pro_id"=>$request->pro_id
            ]);
        }

        return array(
            "flag"=> 1,
            "msg"=> "Success",
            "data"=>''
        );
    }

    public function uploadFile(Request $request){
        $files = $request->file('file_attachment');
        if(!$files)
        {
            return array(
                "status"=> 0,
                "msg"=>"Post is Failed",
                "data"=>'');
        }
        // $testPro = DB::select("SELECT * FROM snap_products WHERE pro_name = ".$request->pro_name);
        // if(count($testPro)){
        //  return array(
        //         "status"=> 0,
        //         "msg"=>"The same product name is already exist",
        //         "data"=>'');   
        // }
        if($request->pro_media_type == 'image'){
            $filename = "product".round(microtime(true) * 1000).".jpg";
        } else {
            $filename = "product".round(microtime(true) * 1000).".mp4";
        }
        request()->file_attachment->move(public_path('uploads/products/'), $filename);

        // $result = DB::insert('INSERT into snap_products (pro_name, pro_description, pro_location, pro_price, pro_currency, pro_currency_symbo, pro_media_type, pro_media_url, pro_customer_id ) values (?, ?, ?, ?, ?, ?, ?, ?, ?)', [$request->pro_name, $request->pro_description, $request->pro_location, $request->pro_price, $request->pro_currency, $request->pro_currency_symbol, $request->pro_media_type, $filename, $request->pro_customer_id]);f
        $id = DB::table('snap_products')->insertGetId([
                'pro_name'=> $request->pro_name,
                'pro_description'=>$request->pro_description,
                'pro_location'=>$request->pro_location,
                'pro_location_id'=>$request->pro_location_id,
                'pro_price'=>$request->pro_price,
                'pro_currency'=>$request->pro_currency,
                'pro_currency_symbo'=>$request->pro_currency_symbol,
                'pro_media_type'=>$request->pro_media_type,
                'pro_media_url'=>$filename,
                'pro_customer_id'=>$request->pro_customer_id,
                'pro_location_id'=>$request->pro_location_id,
            ]);

        $customer_name = DB::select("SELECT c_name FROM snap_customers WHERE c_id =".$request->pro_customer_id.";");
        // return $customer_name;
        if(count($customer_name))
        $this->sendnotitofriend($request->pro_customer_id, 'New post', $request->pro_name." is posted by ".$customer_name[0]->c_name);        
        $this->addFavoritLocation($request->pro_customer_id, $request->pro_location_id);

        $categories = json_decode($request->pro_subcategories);
        foreach($categories as $category){
            DB::table('snap_product_cat')->insertGetId([
                "cat_id"=>$category,
                "pro_id"=>$id
            ]);
        }
        return array(
            "flag"=> 1,
            "msg"=> "Success",
            "data"=>$id
        );
    }

    public function getpacctterms () {
        $result = DB::select("SELECT * FROM snap_pacct_terms");

        return $result;
    }

    public function resetpassword (Request $request) {
        $user = Customer::where('c_phone_number', $request->c_phone_number)->get();
        if(count($user)){
            $newUser = Customer::where('c_phone_number', $request->c_phone_number)
                            ->update(['c_password' => Hash::make($request->new_pwd)]);
            $user[0]->avatar = $user[0]->c_avatar? $this->serverUrl."uploads/customers/".$user[0]->c_avatar : '';
            return array(
                "flag"=>1,
                "msg"=> "Success",
                "data"=>$user[0]
            );
        } else {
            return array(
                "flag"=>0,
                "msg"=> "The phone number is not registered",
                "data"=>''
            );
        }
        return $result;
    }

    public function updatefcmtoken (Request $request) {
        $user = Customer::where('c_id', $request->c_id)
                            ->update(['c_fcm_token' => $request->fcmToken]);
        return array(
            'data' => $result
        );
    }
    public function addnotification($customer_id, $title, $body){
        DB::insert('INSERT into snap_notifications (noti_to_userId, noti_title, noti_body) values (?, ?, ?)', [$customer_id, $title, $body]);
    }

    public function sendpush($customer_id, $title, $body, $notification){
        $customer = Customer::where('c_id', $customer_id)->get();
        $fcm_api_key = $_ENV['FCM_API_KEY'];
        if(count($customer)){
            $response = Http::withToken($fcm_api_key)->post('https://fcm.googleapis.com/fcm/send', [
                'to' => $customer[0]->c_fcm_token,
                'notification' => ["body" => $body,
                                    "title" => $title,
                                    "content_available" => true,
                                    "priority" => "high"
                                ],
                'data' => ["body" => $body,
                            "title" => $title,
                            "content_available" => true,
                            "priority" => "high"
                            ],
            ]);
            if($notification)
            $this->addnotification($customer_id, $title, $body);
        } else {
            return array(
                "flag"=> 1,
                "msg"=>"Account is closed"
            );
        }
    }

    public function simplesendpush(Request $request){
        $customer = Customer::where('c_id', $request->customer_id)->get();
        $fcm_api_key = $_ENV['FCM_API_KEY'];
        if(count($customer)){
            return $response = Http::withToken($fcm_api_key)->post('https://fcm.googleapis.com/fcm/send', [
                'to' => $customer[0]->c_fcm_token,
                'notification' => ["body" => $request->body,
                                    "title" => $request->title,
                                    "content_available" => true,
                                    "priority" => "high"
                                ],
                'data' => ["body" => $request->body,
                            "title" => $request->title,
                            "content_available" => true,
                            "priority" => "high"
                            ],
            ]);
        } else {
            return array(
                "flag"=> 1,
                "msg"=>"Account is closed"
            );
        }
        
    }

    public function getmyproducts(Request $request){
        $products = DB::select('SELECT * FROM snap_products WHERE pro_customer_id = '.$request->c_id);
        
        $result = array();
        foreach($products as $pro){
            $pro->pro_media_url = $this->serverUrl."uploads/products/".$pro->pro_media_url;
            $result[] = $pro;
        }

        return $products;
    }

    public function getfriendprofile(Request $request){
        $user = Customer::where("c_id", $request->c_id)->get();
        if(count($user)){
            $user[0]->avatar = $user[0]->c_avatar? $this->serverUrl."uploads/customers/".$user[0]->c_avatar : '';
            return array(
                "flag" => 1,
                "msg" => '',
                "data" => $user[0]
            );
        } else {
            return array(
                "flag" => 0,
                "msg" => 'This account is closed',
                "data" => ''
            );
        }
    }

    public function getchatinfo(Request $request){
        $channels = DB::select("SELECT * FROM snap_chat_channels WHERE ch_user_a = ".$request->customer_id." OR ch_user_b = \"".$request->customer_id."\"");
        $chat_channels = array();
        $num = 0 ; 
        foreach($channels as $ch){
            $result = DB::select('SELECT * FROM snap_chat_logs WHERE chat_channel = \''.$ch->ch_name.'\' AND chat_from_user != '.$request->customer_id." AND read_at = 0" );
            if(count($result)){
                $num++    ;
            }
            $chat_channels[] = $ch->ch_name;
        }
        return array(
                "flag" => 1,
                "msg" => '',
                "data" => $num,
                "channels" => $chat_channels
            );
    }


    public function requestchat(Request $request){
        $friend = Customer::where("c_id", $request->friend_id)->get();
        if(count($friend)){
            $channel = DB::select("SELECT * FROM snap_chat_channels WHERE (ch_user_a = ".$request->friend_id." AND ch_user_b = ".$request->user_id.") OR (ch_user_a = ".$request->user_id." AND ch_user_b = ".$request->friend_id.");");
            if(count($channel)){
                return array(
                    "flag" => 1,
                    "msg" => 'Channel is already exist',
                    "built_channel"=> 1,
                    "data" => ""
                );
            } else {
                $mine = Customer::where("c_id", $request->user_id)->get();
                $channel_name = 'snapsell'.$request->user_id."-snapsell".$request->friend_id;

                DB::insert('INSERT into snap_chat_channels (ch_user_a, ch_user_b, ch_name) values (?, ?, ?)', [$request->user_id, $request->friend_id, $channel_name]);
                DB::insert('INSERT into snap_chat_logs (chat_channel, chat_from_user, chat_content_type, chat_content) values (?, ?, ?, ?)', [$channel_name, $request->user_id, 'text', 'Hi!']);

                return array(
                    "flag" => 1,
                    "msg" => 'No channel',
                    "built_channel"=> 0,
                    "data" => $channel_name
                );
            }
        } else {
            return array(
                "flag" => 0,
                "msg" => 'This account is Closed',
                "built_channel"=> 0,
                "data" => ""
            );
        }
    }

    public function getdirectchatinfo(Request $request){
        $friend = Customer::where("c_id", $request->friend_id)->get();
        if(count($friend)){
            // get channel info
            $channel = DB::select("SELECT * FROM snap_chat_channels WHERE (ch_user_a = ".$request->friend_id." AND ch_user_b = ".$request->user_id.") OR (ch_user_a = ".$request->user_id." AND ch_user_b = ".$request->friend_id.");");
            
            // get all channels
            $all_channels = DB::select("SELECT * FROM snap_chat_channels WHERE ch_user_a = ".$request->user_id." OR ch_user_b = ".$request->user_id.";");

            if(count($channel)){
                // chatting history
                $logs = DB::select("SELECT * FROM snap_chat_logs WHERE chat_channel = '".$channel[0]->ch_name."'");

                $chat_channel = array();

                foreach($channel as $ch){
                    $chat_channels[] = $ch->ch_name;
                }

                return array(
                    "flag" => 1,
                    "msg" => 'Success',
                    "selected_channel" => $channel[0],
                    "channels" => $chat_channels,
                    "chatting_history" => $logs
                );
            } else {
                return array(
                    "flag" => 0,
                    "msg" => 'Channel is closed',
                    "built_channel"=> 0,
                    "data" => ""
                );
            }
        } else {
            return array(
                "flag" => 0,
                "msg" => 'This account is Closed',
                "built_channel"=> 0,
                "data" => ""
            );
        }
    }

    public function sendmessage(Request $request){
        DB::insert('INSERT into snap_chat_logs (chat_channel, chat_from_user, chat_content_type, chat_content) values (?, ?, ?, ?)', [$request->channel, $request->from_user, $request->type, $request->content]);

        $channel = DB::select("SELECT * from snap_chat_channels WHERE ch_name = '".$request->channel."'");

        if(count($channel)){
            $friend = $channel[0]->ch_user_a == $request->from_user ? $channel[0]->ch_user_b : $channel[0]->ch_user_a;
        }

        $user = Customer::where('c_id', $request->from_user)->get();

        $user_name = $user[0]->c_name;
        if(!$request->presence){
            if($request->type == 'text'){
                $this->sendpush($friend, $request->content, "from ".$user_name, false);
            } else {
                $this->sendpush($friend, "file attached", "from ".$user_name, false);
            }    
        }
        
    }

    public function searchlocations(Request $request){
        $locations = DB::select("SELECT * FROM snap_locations WHERE l_area_en LIKE '%".$request->search."%' OR l_area_ar LIKE '%".$request->search."%' or l_country_en LIKE '%".$request->search."%' or l_country_ar LIKE '%".$request->search."%' ORDER BY l_area_en LIMIT 30;");
        $result = array();
        foreach($locations as $location){
            if($request->isRTL){
                $location->area_name = $location->l_area_ar ? $location->l_area_ar : $location->l_area_en;
                $location->country_name = $location->l_country_ar ? $location->l_country_ar : $location->l_country_ar;
            } else {
                $location->area_name = $location->l_area_en;
                $location->country_name = $location->l_country_en;
            }
            $result[] = $location;
        }
        return $locations;
    }

    public function favoritlocations(Request $request){
        $location_ids = DB::select("SELECT * FROM snap_favorite_locations WHERE c_id = ".$request->c_id." ORDER BY visited_times DESC LIMIT 10");

        $locations = array();
        foreach($location_ids as $l_id){
            $location = DB::select("SELECT * FROM snap_locations WHERE l_id = ".$l_id->l_id);
            if(count($location)){
                $locations[] = $location[0];    
            }
        }

        $result = array();

        foreach($locations as $location){
            if($request->isRTL){
                $location->area_name = $location->l_area_ar ? $location->l_area_ar : $location->l_area_en;
                $location->country_name = $location->l_country_ar ? $location->l_country_ar : $location->l_country_ar;
            } else {
                $location->area_name = $location->l_area_en;
                $location->country_name = $location->l_country_en;
            }
            $result[] = $location;
        }
        return array(
            "data"=>$locations
        );
    }

    public function getchatlist(Request $request){
        $channels = DB::select("SELECT * FROM snap_chat_channels WHERE ch_user_a = ".$request->customer_id." OR ch_user_b = \"".$request->customer_id."\"");
        $channel_results = array();

        foreach($channels as $ch){
            $friend_id = 0;
            if($ch->ch_user_a != $request->customer_id){
                $friend_id = $ch->ch_user_a;
            } else {
                $friend_id = $ch->ch_user_b;
            }

            $friend = Customer::where('c_id', $friend_id)->get();
            
            if(count($friend)){
                $ch->friend = array(
                    "c_id" => $friend[0]->c_id,
                    "c_name" => $friend[0]->c_name,
                    "avatar" => $friend[0]->c_avatar? $this->serverUrl."uploads/customers/".$friend[0]->c_avatar : ''
                ); 
                // $channel_results[] = $ch;
            }
            
            $unread_msg = DB::select("SELECT * FROM snap_chat_logs WHERE chat_channel = '".$ch->ch_name."' AND chat_from_user != ".$request->customer_id." AND  read_at = 0");
            $last_msg = DB::select("SELECT * FROM snap_chat_logs WHERE chat_channel = '".$ch->ch_name."' ORDER BY created_at DESC LIMIT 1");

            $ch->unread_num = count($unread_msg);
            if(count($last_msg)){
                $ch->last_msg = array(
                    "msg"=> $last_msg[0]->chat_content_type == 'text' ? $last_msg[0] ? $last_msg[0]->chat_content : null : "file attached" ,
                    "content_type"=> $last_msg[0] ? $last_msg[0]->chat_content_type : null,
                    "created_at"=> $last_msg[0] ? $last_msg[0]->created_at : null,
                );                
            } else {
                $ch->last_msg = array(
                    "msg"=> "" ,
                    "content_type"=> "",
                    "created_at"=> '',
                );    
            }
            
        }

        return $channels;
    }

    public function getnewnotifications (Request $request){
        $channels = DB::select("SELECT * FROM snap_chat_channels WHERE ch_user_a = ".$request->customer_id." OR ch_user_b = \"".$request->customer_id."\"");
        $unread_msg_num = 0;
        foreach($channels as $ch){
            
            $unread_msg = DB::select("SELECT * FROM snap_chat_logs WHERE chat_channel = '".$ch->ch_name."' AND chat_from_user != ".$request->customer_id." AND  read_at = 0");
            $unread_msg_num += count($unread_msg);
        }

        $notifications = DB::select("SELECT * FROM snap_notifications WHERE noti_to_userId = ".$request->customer_id." AND read_at IS NULL");

        return array(
            "unread_msg" => $unread_msg_num,
            "unread_notification" => count($notifications)
        );
    }

    public function getchannels(Request $request){
        $channels = DB::select("SELECT * FROM snap_chat_channels WHERE ch_user_a = ".$request->customer_id." OR ch_user_b = \"".$request->customer_id."\"");

        $result = array();

        foreach($channels as $ch){
            $result[] = $ch->ch_name;
        }

        return array(
            "channels" => $result
        );
    }

    public function readmsg(Request $request){
        DB::table('snap_chat_logs')
                ->where('chat_channel', $request->channel)
                ->where('chat_from_user', $request->customer_id)
                ->update(['read_at' => 1]);
    }

    public function uploadFileChat(Request $request){
        $files = $request->file('file_attachment');
        if(!$files)
        {
            return array(
                "status"=> 0,
                "msg"=>"Post is Failed",
                "data"=>'');
        }

        if($request->type == 'image'){
            $filename = "snapsell_chatting_".round(microtime(true) * 1000).".jpg";
        } else {
            $filename = "snapsell_chatting_".round(microtime(true) * 1000).".mp4";
        }
        request()->file_attachment->move(public_path('uploads/chatting/'), $filename);
        

        DB::insert('INSERT into snap_chat_logs (chat_channel, chat_from_user, chat_content_type, chat_content) values (?, ?, ?, ?)', [$request->channel, $request->from_user, $request->type, $filename]);

        $channel = DB::select("SELECT * from snap_chat_channels WHERE ch_name = '".$request->channel."'");

        if(count($channel)){
            $friend = $channel[0]->ch_user_a == $request->from_user ? $channel[0]->ch_user_b : $channel[0]->ch_user_a;
        }

        $user = Customer::where('c_id', $request->from_user)->get();

        $user_name = $user[0]->c_name;
        if(!$request->presence){
            if($request->type == 'text'){
                $this->sendpush($friend, $request->content, "from ".$user_name, false);
            } else {
                $this->sendpush($friend, "file attached", "from ".$user_name, false);
            }    
        }

        return array(
            "flag"=> 1,
            "msg"=> "Success",
            "fileUri"=>$filename
        );
    }

    public function getnotifications (Request $request) {
        $pageNum = $request->page_num ? $request->page_num : 0;
        $limit = $request->page_limit ? $request->page_limit : 10;
        $userId = $request->customer_id ? $request->customer_id : 0;

        $query = "SELECT * FROM snap_notifications WHERE noti_to_userId = ".$userId." ORDER BY created_at DESC LIMIT ".$limit." OFFSET ".($pageNum*$limit);
        $result = DB::select($query);

        return array(
            "flag" => 1,
            "msg" => "success",
            "pageNum" => $pageNum,
            "limit" => $limit,
            "result" => $result
        );
    }

    public function readnoti (Request $request){
        DB::table('snap_notifications')
                ->where('noti_to_userId', $request->customer_id)
                ->update(['read_at'=>date('Y-m-d H:i')]);
    }

    public function closenoti (Request $request){
        DB::delete('delete from snap_notifications where noti_id = :id' ,['id' => $request->noti_id]);
    }

    public function getproreviews(Request $request){
        $reviews = DB::select('SELECT * FROM snap_reviews WHERE rev_to_proid = '.$request->pro_id);
        $result = array();

        foreach($reviews as $rev){
            $reviewer = DB::select('SELECT * FROM snap_customers WHERE c_id = '.$rev->rev_from_uid);
            if(count($reviewer)){
                $reviewer[0]->avatar = $reviewer[0]->c_avatar ? $this->serverUrl."uploads/customers/".$reviewer[0]->c_avatar : "" ;
                $rev->reviewer = $reviewer[0];
            } else {
                $rev->reviewer = array(
                    "c_id" => 0,
                    'avatar' => '',
                    'c_name' => ''
                );
            }
            $result[] = $rev;
        }
        return array(
            "flag"=> 1,
            "data"=> $result,
        );
    }

    public function leavereview(Request $request){
        $review = $request->content;
        $rating = $request->rating;
        $customer_id = $request->customer_id;
        $pro_id = $request->pro_id;

        $pro_owner_id = DB::select("SELECT snap_customers.c_id FROM snap_products LEFT JOIN snap_customers ON snap_customers.c_id = snap_products.pro_customer_id WHERE pro_id = ".$pro_id);
        if(count($pro_owner_id)){
            if($customer_id == $pro_owner_id[0]->c_id){
                return array(
                    "flag" => 0,
                    "msg" => "You are owner of this product"
                );
            }
        }

        DB::insert('INSERT into snap_reviews (rev_to_uid, rev_from_uid, rev_to_proid, rev_point, rev_content) values (?, ?, ?, ?, ?)', [$pro_owner_id[0]->c_id, $customer_id, $pro_id, $rating, $review]);    

        return array(
            "flag" => 1,
            "msg" => "Left review successfully"
        );
    }

    public function getlocations(Request $request) {
        if($request->RTL)
        $query = "SELECT l_id AS `value`, CONCAT(l_area_ar, ', ', l_ios2_country_code) AS label FROM snap_locations;";

        $query = "SELECT l_id AS `value`, CONCAT(l_area_en, ', ', l_ios2_country_code) AS label FROM snap_locations;";

        $result = DB::select($query);

        return $result;
    }

    public function getparentcategory(Request $request){
        $category = DB::select("SELECT t1.pro_id, t1.cat_id, snap_category_sub.sCat_parent_cat FROM (SELECT * FROM snap_product_cat WHERE pro_id = ".$request->pro_id.") AS t1 LEFT JOIN snap_category_sub ON t1.cat_id = snap_category_sub.sCat_id");
        
        if(count($category)){
            $temp = array();

            foreach($category as $cat){
                $temp[] = $cat->cat_id;
            }
            return array(
                "flag" => 1,
                "sub_id"=>$temp,
                "cat_id"=>$category[0]->sCat_parent_cat
            );
        } else {
            return array(
                "flag" => 0,
                "sub_id"=>'',
                "cat_id"=>''
            );
        }
    }
}
