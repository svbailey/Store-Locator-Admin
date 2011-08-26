<?php

class StoreTableGateway {

	protected $db;
	protected $table;
	protected $column_map;
	
	const GEOCODE_STATUS_TRUE = 1;
	const GEOCODE_STATUS_FALSE = 0;

	public function backup( $file ) {
		$sql = sprintf( sprintf( 'select * into outfile :file from %s', $this->table ) );
		echo $sql . $file;
		$stmnt = $this->db->prepare( $sql );
		$stmnt->bindValue( ':file', $file );
		if ( $stmnt->execute() ) {
			return true;
		}
		return false;
	}

	public function restore( $file ) {
		$sql = sprintf( sprintf( 'truncate table %1$s; load data infile :file into table %1$s', $this->table ) );
		$stmnt = $this->db->prepare( $sql );
		$stmnt->bindValue( ':file', $file );
		if ( $stmnt->execute() ) {
			return true;
		}
		return false;
	}

	public function __construct( PDO $db, $table, array $column_map ) {
		$this->db = $db;
		$this->table = $table;
		$this->column_map = $column_map;
	}

	public function getCount( array $search_params=null, $geocode_status=null ) {
		$sql = sprintf( 'select count(id) from %s %s', $this->table,isset( $search_params ) ? $this->buildSearchString( $search_params, $geocode_status ) : '' );
		unset( $search_params['geocode_status'] );
		$stmnt = $this->db->prepare( $sql );
		if ( is_array( $search_params ) ) {
			foreach( $search_params as $sp ) {
				if ( strtolower( $sp[2] ) != 'null' && strtolower( $sp[2] ) != 'not null' ) {
					$stmnt->bindValue( ':'.$sp[0], $sp[2] );
				}
			}
		}
		$stmnt->execute();
		return $stmnt->fetchColumn();
	}

	public function getStores( $start, $length, array $search_params=null, $geocode_status=null ) {
		$sql = sprintf( 'select * from %s %s limit :start, :length', $this->table, isset( $search_params ) ? $this->buildSearchString( $search_params, $geocode_status ) : '' );
		$stmnt = $this->db->prepare( $sql );
		$stmnt->bindValue( ':start', $start, PDO::PARAM_INT );
		$stmnt->bindValue( ':length', $length, PDO::PARAM_INT );
		if ( is_array( $search_params ) ) {
			foreach( $search_params as $sp ) {
				$stmnt->bindValue( ':'.$sp[0], $sp[2] );
			}
		}
		$stmnt->execute();
		$stores = array();
		foreach( $stmnt->fetchAll( PDO::FETCH_ASSOC ) as $data ) {
			$stores[] = new Store( $this->column_map, $data );
		}
		return $stores;
	}

	private function buildSearchString( array $search_params, $geocode_status ) {
		$columns = implode( ' and ', array_map( function($a){ return sprintf( '%s %s :%s', $a[0], $a[1], $a[0] ); }, $search_params ) );
		$sql = sprintf( 'where 1 = 1%s', $columns ? ' and ' : '' ) . $columns; 

		if ( $geocode_status === self::GEOCODE_STATUS_FALSE ) {
			$sql .= sprintf( ' and ( %1$s is null or %1$s = 0 and %2$s is null or %2$s = 0 )', $this->column_map['lat'], $this->column_map['lng'] );
		}
		elseif ( $geocode_status === self::GEOCODE_STATUS_TRUE ) {
			$sql .= sprintf( ' and ( %1$s is not null and %1$s != 0 and %2$s is not null and %2$s != 0 )', $this->column_map['lat'], $this->column_map['lng'] );
		}
		
		return $sql;
	}

	function getColumns() {
		$tmp_columns = $this->db->query( sprintf( 'show columns from %s', $this->table ) )->fetchAll( PDO::FETCH_ASSOC );
		foreach( $tmp_columns as $column ) {
			$type = $column['Type'];
			if ( strpos( $type, 'text' ) !== FALSE ) {
				$columns[$column['Field']] = array (
					'type'		=> 'textarea'
				);
			}
			elseif ( strpos( $type, 'enum' ) !== FALSE ) {
				$columns[$column['Field']] = array (
					'type'		=> 'select',
					'values'	=> array_map( function( $v ) use( $type ){ return str_replace( "'", '', $v ); }, explode( ',', end( explode( '(', trim( $type, ')' ) ) ) ) )
				);
				sort( $columns[$column['Field']]['values'] );
			}
			else {
				$columns[$column['Field']] = array (
					'type'		=> 'textbox'
				);
			}
		}
		return $columns;
	}

	function getStore( $id ) {
		$sql = sprintf( 'select * from %s where id=:id', $this->table );
		$stmnt = $this->db->prepare( $sql );
		$stmnt->bindValue( ':id', $id, PDO::PARAM_INT );
		$stmnt->execute();
		$data = current( $stmnt->fetchAll( PDO::FETCH_ASSOC ) );
		return new Store( $this->column_map, $data );
	}

	function deleteStore( $id ) {
		$sql = sprintf( 'delete from %s where %s = :id', $this->table, $this->column_map['id'] );
		$stmnt = $this->db->prepare( $sql );
		$stmnt->bindValue( ':id', $id );
		if( $stmnt->execute() && $stmnt->rowCount() ) {
			return true;
		}
		return false;
	}

	function createStore( Store $store ) {
		$vars = $store->getData();
		unset( $vars['id'] );
		$sql = sprintf( 'insert into %s (%s) values(%s)', $this->table, implode( ',', array_keys( $vars ) ), implode( ',', array_map( function( $v ) { return ':'.$v; }, array_keys( $vars ) ) ) );
		$stmnt = $this->db->prepare( $sql );
		foreach( $vars as $var => $val ) {
			$stmnt->bindValue( ':'.$var, $val );
		}
		if( $stmnt->execute() && $stmnt->rowCount() ) {
			return $this->db->lastInsertId();
		}
		return false;
	}

	function saveStore( Store $store ) {
		$id = $store->getID();
		$store_array = $store->getData();
		unset( $store_array['id'] );
		foreach( $store_array as $property => $value ) {
			if ( strpos( $property, '_' ) === 0 ) {
				unset( $store_array[$property] );
			}
		}
		$cm = $this->column_map;
		$sql = sprintf( 'update %s set %s where id = :id',
			$this->table,
			implode( ', ', array_map( function( $c ) { return sprintf( '%1$s = :%1$s', $c ); }, array_keys( $store_array ) ) )
		);
		$stmnt = $this->db->prepare( $sql );
		$stmnt->bindValue( ':id', $id );
		foreach( $store_array as $property => $value ) {
			$stmnt->bindValue( ':'.$property, $value );
		}
		if ( $stmnt->execute() ) {
			return true;
		}
		return false;
	}

	function validateTable() {
		foreach( $this->db->query( sprintf( 'show columns from %s', $this->table ) )->fetchAll( PDO::FETCH_ASSOC ) as $c ) {
			$columns[$c['Field']] = true;
		}
		return isset( $columns[$this->column_map['id']], $columns[$this->column_map['lat']], $columns[$this->column_map['lng']] );
	}

}

