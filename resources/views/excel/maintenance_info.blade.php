@foreach($info as $item)[车辆保养描述：{{ $item['description'] }}；配件名称：{{ $item['part_name'] }}；配件金额：{{ $item['part_cost'] }}；配件数量：{{ $item['part_quantity'] }}]@endforeach
