<?php
namespace RoutesPro\Rest;

use RoutesPro\Mapping\MappingRepository;
use RoutesPro\Mapping\MappingService;

if (!defined('ABSPATH')) exit;

class MappingController {
    public function register_routes(): void {
        register_rest_route('fieldflow/v1','/mapping/targets',['methods'=>'GET','callback'=>[$this,'targets'],'permission_callback'=>[$this,'permission']]);
        register_rest_route('fieldflow/v1','/mapping/summary',['methods'=>'GET','callback'=>[$this,'summary'],'permission_callback'=>[$this,'permission']]);
    }
    public function permission(){return current_user_can('routespro_manage')?true:new \WP_Error('fieldflow_forbidden','Sem permissões para gerir mapping.',['status'=>403]);}
    public function targets(\WP_REST_Request $request){
        $entity=sanitize_key((string)$request->get_param('entity_type'));if(!in_array($entity,['store','product'],true))$entity='store';
        $client=absint($request->get_param('client_id'));$project=absint($request->get_param('project_id'));$q=sanitize_text_field((string)$request->get_param('q'));
        if(function_exists('mb_strlen')?mb_strlen($q)<2:strlen($q)<2)return rest_ensure_response([]);
        $rows=MappingService::searchTargets($entity,$client,$project,$q,30);$out=[];
        foreach($rows as $row){$out[]=['id'=>(int)$row['id'],'label'=>$entity==='product'?(string)$row['product_name']:(string)$row['name'],'secondary'=>$entity==='product'?trim((string)($row['reference']??'').' · '.(string)($row['cardex_name']??'')):trim((string)($row['external_ref']??'').' · '.(string)($row['city']??''))];}
        return rest_ensure_response($out);
    }
    public function summary(\WP_REST_Request $request){return rest_ensure_response(MappingRepository::counts(['client_id'=>absint($request->get_param('client_id')),'project_id'=>absint($request->get_param('project_id'))]));}
}
