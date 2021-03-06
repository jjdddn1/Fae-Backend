<?php

namespace App\Api\v1\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Dingo\Api\Routing\Helpers;
use Dingo\Api\Exception\UpdateResourceFailedException;
use App\Sessions;
use Validator;
use DB;
use Phaza\LaravelPostgis\Eloquent\PostgisTrait;
use Phaza\LaravelPostgis\Geometries\Point;
use Phaza\LaravelPostgis\Geometries\Geometry;

class MapController extends Controller
{
    use Helpers;
    use PostgisTrait;
    
    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function getMap()
    {
        $this->getMapValidation($this->request);
        $location = new Point($this->request->geo_latitude,$this->request->geo_longitude);
        $longitude = $this->request->geo_longitude;
        $latitude = $this->request->geo_latitude;
        $radius = $this->request->has('radius') ? $this->request->radius:200;
        $max_count = $this->request->has('max_count') ? $this->request->max_count:30;
        $type = array();
        if($this->request->has('type'))
        {
            $types = explode(',',$this->request->type);
            foreach($types as $t)
            {
                $t = trim($t);
                switch($t)
                {
                    case 'user':
                        $type[] = 'user';
                        break;
                    case 'comment':
                        $type[] = 'comment';
                        break;
                    case 'media':
                        $type[] = 'media';
                        break;
                    case 'faevor':
                        $type[] = 'faevor';
                        break;
                    default:
                        return $this->response->errorNotFound();
                }
            }
        }
        else
        {
            array_push($type, 'user','comment','media','faevor');
        }
        $info = array();
        foreach($type as $t)
        {
            if($max_count <= 0)
            {
                break;
            }
            switch($t)
            {
                case 'user':
                    $sessions = DB::select("SELECT user_id,location,created_at FROM sessions s WHERE st_dwithin(s.location,ST_SetSRID(ST_Point(:longitude, :latitude),4326),:radius,true) LIMIT :max_count", array('longitude' => $longitude, 'latitude' => $latitude, 'radius' => $radius, 'max_count' => $max_count));
                    foreach($sessions as $session)
                    {
                        $location = Geometry::fromWKB($session->location);
                        $locations = array();
                        for($i = 0; $i < 5; $i++)
                        {
                            $distance = mt_rand(1,100);
                            $degree = mt_rand(0,360);
                            $locations_original = DB::select("select ST_AsText(ST_Project(ST_SetSRID(ST_Point(:longitude, :latitude),4326),:distance, radians(:degree)))", array('longitude' => $location->getLng(),'latitude'=>$location->getLat(),'distance'=>$distance,'degree'=>$degree));
                            $locations[] = Point::fromWKT($locations_original[0]->st_astext);
                        } 
                        $info[] = ['type'=>'user','user_id' => $session->user_id,'geolocation'=>[['latitude'=>$locations[0]->getLat(),
                        'longitude'=>$locations[0]->getLng()],['latitude'=>$locations[1]->getLat(),
                        'longitude'=>$locations[1]->getLng()],['latitude'=>$locations[2]->getLat(),
                        'longitude'=>$locations[2]->getLng()],['latitude'=>$locations[3]->getLat(),
                        'longitude'=>$locations[3]->getLng()],['latitude'=>$locations[4]->getLat(),
                        'longitude'=>$locations[4]->getLng()]],'created_at'=>$session->created_at];
                        $max_count--;
                    }
                    break;
                case 'comment':
                    $comments = DB::select("SELECT id,user_id,content,geolocation,created_at FROM comments c WHERE st_dwithin(c.geolocation,ST_SetSRID(ST_Point(:longitude, :latitude),4326),:radius,true) LIMIT :max_count", array('longitude' => $longitude, 'latitude'=> $latitude, 'radius' => $radius, 'max_count' => $max_count));
                    foreach($comments as $comment)
                    {
                        $location = Geometry::fromWKB($comment->geolocation);
                        $info[] = ['type'=>'comment','comment_id' => $comment->id,'user_id' => $comment->user_id,'content' => $comment->content ,'geolocation'=>['latitude'=>$location->getLat(), 'longitude'=>$location->getLng()],'created_at'=>$comment->created_at];
                        $max_count--;
                    }
                    break;
                case 'media':
                    $medias = DB::select("SELECT * FROM medias m WHERE st_dwithin(m.geolocation,ST_SetSRID(ST_Point(:longitude, :latitude),4326),:radius,true) LIMIT :max_count", array('longitude' => $longitude, 'latitude'=> $latitude, 'radius' => $radius, 'max_count' => $max_count));
                    foreach ($medias as $media)
                    {
                        $location = Geometry::fromWKB($media->geolocation);
                        $info[] = ['type'=>'media', 'media_id' => $media->id, 'user_id' => $media->user_id, 'file_ids' => explode(';', $media->file_ids), 'tag_ids' => explode(';', $media->tag_ids), 'description' => $media->description, 'geolocation'=>['latitude' => $location->getLat(), 'longitude' => $location->getLng()], 'created_at' => $media->created_at];
                        $max_count--;
                    }
                    break;
                case 'faevor':
                    $faevors = DB::select("SELECT * FROM faevors f WHERE st_dwithin(f.geolocation,ST_SetSRID(ST_Point(:longitude, :latitude),4326),:radius,true) LIMIT :max_count", array('longitude' => $longitude, 'latitude'=> $latitude, 'radius' => $radius, 'max_count' => $max_count));
                    foreach ($faevors as $faevor )
                    {
                        $location = Geometry::fromWKB($faevor->geolocation);
                        $file_ids = is_null($faevor->file_ids) ? null : explode(';', $faevor->file_ids);
                        $tag_ids = is_null($faevor->tag_ids) ? null : explode(';', $faevor->tag_ids);
                        $info[] = ['type'=>'faevor', 'faevor_id' => $faevor->id, 'user_id' => $faevor->user_id, 'file_ids' => $file_ids, 'tag_ids' => $tag_ids, 'description' => $faevor->description, 'name' => $faevor->name, 'budget' => $faevor->budget, 'bonus' => $faevor->bonus, 'due_time' => $faevor->due_time, 'expire_time' => $faevor->expire_time, 'geolocation' => ['latitude' => $location->getLat(), 'longitude' => $location->getLng()], 'created_at' => $faevor->created_at];
                        $max_count--;
                    }
                    break;
                default:
                    return $this->response->errorNotFound();
            }
        }
        return $this->response->array($info);
    }

    private function getMapValidation(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'geo_longitude' => 'required|numeric|between:-180,180',
            'geo_latitude' => 'required|numeric|between:-90,90',
            'radius' => 'filled|integer|min:0',
            'type' => 'filled|string',
            'max_count' => 'filled|integer|between:0,100',
        ]);
        if($validator->fails())
        {
            throw new UpdateResourceFailedException('Could not get map.',$validator->errors());
        }
    }

    public function updateUserLocation()
    {        
        $this->locationValidation($this->request);
        $session = Sessions::find($this->request->self_session_id);
        if(is_null($session))
        {
            return $this->response->errorNotFound();
        }
        if($session->is_mobile)
        {
            $session->location = new Point($this->request->geo_latitude,$this->request->geo_longitude);
            $session->save();
            return $this->response->created();
        }
        else
        {
            throw new UpdateResourceFailedException('current user is not active');
        }
    }

    private function locationValidation(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'geo_longitude' => 'required|numeric|between:-180,180',
            'geo_latitude' => 'required|numeric|between:-90,90',
        ]);
        if($validator->fails())
        {
            throw new UpdateResourceFailedException('Could not update user location.',$validator->errors());
        }
    }
}
