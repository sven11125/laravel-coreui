<?php

use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

/*
Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});
*/
Route::post('/testfriend', 'ApiController@testfriend')->name('/testfriend');

Route::post('/signup', 'ApiController@signup')->name('/signup');
Route::post('/resendcode', 'ApiController@resendcode')->name('/resendcode');
Route::post('/login', 'ApiController@login')->name('/login');
Route::post('/verify', 'ApiController@verify')->name('/verify');

Route::post('/simplesendpush', 'ApiController@simplesendpush')->name('/simplesendpush');

Route::get('/getcategories', 'ApiController@getcategories')->name('/getcategories');
Route::post('/getsubcategory', 'ApiController@getsubcategory')->name('/getsubcategory');
Route::post('/filterproducts', 'ApiController@filterproducts')->name('/filterproducts');
Route::post('/getprofile', 'ApiController@getprofile')->name('/getprofile');
Route::post('/getreviews', 'ApiController@getreviews')->name('/getreviews');
Route::post('/getproducts', 'ApiController@getproducts')->name('/getproducts');
Route::post('/togglefollow', 'ApiController@togglefollow')->name('/togglefollow');
Route::post('/getfollow', 'ApiController@getfollow')->name('/getfollow');
Route::post('/uploadFile', 'ApiController@uploadFile')->name('/uploadFile');
Route::post('/editFile', 'ApiController@editFile')->name('/editFile');
Route::post('/getpacctterms', 'ApiController@getpacctterms')->name('/getpacctterms');
Route::post('/resetpassword', 'ApiController@resetpassword')->name('/resetpassword');
Route::post('/updatefcmtoken', 'ApiController@updatefcmtoken')->name('/updatefcmtoken');
Route::post('/getmyproducts', 'ApiController@getmyproducts')->name('/getmyproducts');
Route::post('/getlocations', 'ApiController@getlocations')->name('/getlocations');


Route::post('/getfriendprofile', 'ApiController@getfriendprofile')->name('/getfriendprofile');
Route::post('/getchatinfo', 'ApiController@getchatinfo')->name('/getchatinfo');
Route::post('/requestchat', 'ApiController@requestchat')->name('/requestchat');
Route::post('/getdirectchatinfo', 'ApiController@getdirectchatinfo')->name('/getdirectchatinfo');
Route::post('/sendmessage', 'ApiController@sendmessage')->name('/sendmessage');
Route::post('/getchatlist', 'ApiController@getchatlist')->name('/getchatlist');
Route::post('/getchannels', 'ApiController@getchannels')->name('/getchannels');
Route::post('/readmsg', 'ApiController@readmsg')->name('/readmsg');
Route::post('/uploadFileChat', 'ApiController@uploadFileChat')->name('/uploadFileChat');


Route::post('/getnewnotifications', 'ApiController@getnewnotifications')->name('/getnewnotifications');

Route::get('/searchlocations', 'ApiController@searchlocations')->name('/searchlocations');
Route::post('/favoritlocations', 'ApiController@favoritlocations')->name('/favoritlocations');

Route::post('/getnotifications', 'ApiController@getnotifications')->name('/getnotifications');
Route::post('/closenoti', 'ApiController@closenoti')->name('/closenoti');
Route::post('/readnoti', 'ApiController@readnoti')->name('/readnoti');
Route::post('/getproreviews', 'ApiController@getproreviews')->name('/getproreviews');

Route::post('/leavereview', 'ApiController@leavereview')->name('/leavereview');
Route::post('/getparentcategory', 'ApiController@getparentcategory')->name('/getparentcategory');

Route::post('/testSMS', 'ApiController@testSMS')->name('/testSMS');
