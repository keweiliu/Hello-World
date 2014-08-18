<?php
  
class Tapatalk_Input extends XenForo_Input {
	public function filterExternal(array $filters, array $sourceData){
		$this->_request = null;
		$this->_sourceData = $sourceData;
		$data = array();
		$i = 0;
		
		foreach ($filters AS $variableName => $filterData)
		{
			$data[$variableName] = $this->filterSingle($i, $filterData);
			$i++;
		}

		return $data;        
	}    
	
	public function filterSingleExternal($filterData, $sourceData, array $options = array())
	{
		$this->_request = null;
		$this->_sourceData = $sourceData;
		return $this->filterSingle(0, $filterData, $options);
	}
}
