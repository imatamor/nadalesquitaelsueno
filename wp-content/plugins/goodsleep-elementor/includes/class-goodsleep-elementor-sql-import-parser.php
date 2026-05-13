<?php
/**
 * Nombre: Goodsleep_Elementor_SQL_Import_Parser
 * Descripción: Interpreta dumps SQL simples basados en sentencias INSERT INTO y
 * los transforma en tablas, columnas y filas consumibles por la herramienta interna.
 * Uso: Instanciarlo desde el plugin y llamar parse_dump() con el contenido del archivo SQL.
 * Notas: Está diseñado para dumps tabulares simples, no para cubrir dialectos SQL complejos.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Goodsleep_Elementor_SQL_Import_Parser {
	/**
	 * Nombre: parse_dump
	 * Descripción: Recorre el dump completo, separa sentencias válidas y arma una
	 * estructura homogénea de tablas con conteo de filas y muestra inicial.
	 * Uso: parse_dump( $sql ) al recibir el archivo subido desde admin.
	 *
	 * @param string $sql Contenido del archivo SQL.
	 * @return array<string,mixed>|WP_Error
	 */
	public function parse_dump( $sql ) {
		$sql = (string) $sql;
		if ( '' === trim( $sql ) ) {
			return new WP_Error( 'goodsleep_empty_sql', __( 'El archivo SQL esta vacio.', 'goodsleep-elementor' ) );
		}

		$statements = $this->split_sql_statements( $sql );
		$tables     = array();

		foreach ( $statements as $statement ) {
			$insert = $this->parse_insert_statement( $statement );
			if ( is_wp_error( $insert ) || empty( $insert ) ) {
				continue;
			}

			$table_name = $insert['table'];
			if ( empty( $tables[ $table_name ] ) ) {
				$tables[ $table_name ] = array(
					'name'    => $table_name,
					'columns' => $insert['columns'],
					'rows'    => array(),
				);
			}

			foreach ( $insert['rows'] as $row ) {
				$tables[ $table_name ]['rows'][] = $row;
			}
		}

		if ( empty( $tables ) ) {
			return new WP_Error( 'goodsleep_sql_without_insert_rows', __( 'No se encontraron sentencias INSERT INTO validas dentro del SQL.', 'goodsleep-elementor' ) );
		}

		foreach ( $tables as $table_name => $table ) {
			$tables[ $table_name ]['row_count'] = count( $table['rows'] );
			$tables[ $table_name ]['sample']    = array_slice( $table['rows'], 0, 5 );
		}

		return array(
			'tables' => $tables,
		);
	}

	/**
	 * Nombre: split_sql_statements
	 * Descripción: Divide el SQL en sentencias individuales sin romper cadenas,
	 * comentarios ni bloques comentados.
	 * Uso: Helper interno previo al parseo de INSERT INTO.
	 *
	 * @param string $sql SQL crudo.
	 * @return array<int,string>
	 */
	protected function split_sql_statements( $sql ) {
		$statements = array();
		$current    = '';
		$length     = strlen( $sql );
		$in_string  = false;
		$quote_char = '';

		for ( $index = 0; $index < $length; $index++ ) {
			$char = $sql[ $index ];
			$next = $index + 1 < $length ? $sql[ $index + 1 ] : '';

			if ( ! $in_string && '-' === $char && '-' === $next ) {
				while ( $index < $length && "\n" !== $sql[ $index ] ) {
					$index++;
				}
				continue;
			}

			if ( ! $in_string && '#' === $char ) {
				while ( $index < $length && "\n" !== $sql[ $index ] ) {
					$index++;
				}
				continue;
			}

			if ( ! $in_string && '/' === $char && '*' === $next ) {
				$index += 2;
				while ( $index < $length - 1 && ! ( '*' === $sql[ $index ] && '/' === $sql[ $index + 1 ] ) ) {
					$index++;
				}
				$index++;
				continue;
			}

			$current .= $char;

			if ( $in_string ) {
				if ( '\\' === $char ) {
					if ( $index + 1 < $length ) {
						$current .= $sql[ $index + 1 ];
						$index++;
					}
					continue;
				}

				if ( $quote_char === $char ) {
					if ( '\'' === $char && '\'' === $next ) {
						$current .= $next;
						$index++;
						continue;
					}

					$in_string  = false;
					$quote_char = '';
				}

				continue;
			}

			if ( '\'' === $char || '"' === $char ) {
				$in_string  = true;
				$quote_char = $char;
				continue;
			}

			if ( ';' === $char ) {
				$trimmed = trim( $current );
				if ( '' !== $trimmed ) {
					$statements[] = $trimmed;
				}
				$current = '';
			}
		}

		$trimmed = trim( $current );
		if ( '' !== $trimmed ) {
			$statements[] = $trimmed;
		}

		return $statements;
	}

	/**
	 * Nombre: parse_insert_statement
	 * Descripción: Extrae nombre de tabla, columnas y grupos VALUES desde una
	 * sentencia INSERT INTO compatible con el importador interno.
	 * Uso: Se ejecuta sobre cada sentencia detectada por split_sql_statements().
	 *
	 * @param string $statement Sentencia SQL.
	 * @return array<string,mixed>|WP_Error|array<int,mixed>
	 */
	protected function parse_insert_statement( $statement ) {
		$statement = trim( (string) $statement );

		if ( ! preg_match( '/^INSERT\s+INTO\s+(.+?)\s*\((.+?)\)\s*VALUES\s*(.+)$/is', $statement, $matches ) ) {
			return array();
		}

		$table_name = $this->normalize_table_name( $matches[1] );
		$columns    = $this->parse_column_list( $matches[2] );
		$rows       = $this->parse_values_groups( $matches[3], $columns );

		if ( is_wp_error( $rows ) ) {
			return $rows;
		}

		return array(
			'table'   => $table_name,
			'columns' => $columns,
			'rows'    => $rows,
		);
	}

	/**
	 * Normaliza el nombre de tabla.
	 *
	 * @param string $table_reference Referencia SQL.
	 * @return string
	 */
	protected function normalize_table_name( $table_reference ) {
		$table_reference = trim( preg_replace( '/\s+/', ' ', (string) $table_reference ) );
		$table_reference = trim( $table_reference, '` ' );

		if ( false !== strpos( $table_reference, '.' ) ) {
			$parts           = explode( '.', $table_reference );
			$table_reference = end( $parts );
		}

		return sanitize_key( str_replace( '`', '', $table_reference ) );
	}

	/**
	 * Parsea la lista de columnas del INSERT.
	 *
	 * @param string $columns_sql SQL entre parentesis.
	 * @return array<int,string>
	 */
	protected function parse_column_list( $columns_sql ) {
		$columns = array_map( 'trim', explode( ',', (string) $columns_sql ) );

		return array_values(
			array_filter(
				array_map(
					static function ( $column ) {
						return sanitize_key( trim( str_replace( '`', '', (string) $column ) ) );
					},
					$columns
				)
			)
		);
	}

	/**
	 * Nombre: parse_values_groups
	 * Descripción: Separa cada grupo de VALUES respetando strings y alinea cada
	 * valor con la columna correspondiente para construir filas asociativas.
	 * Uso: Helper interno después de parsear la sentencia INSERT.
	 *
	 * @param string $values_sql SQL de VALUES.
	 * @param array<int,string> $columns Columnas detectadas.
	 * @return array<int,array<string,string>>
	 */
	protected function parse_values_groups( $values_sql, $columns ) {
		$groups = array();
		$length = strlen( (string) $values_sql );
		$depth  = 0;
		$buffer = '';
		$in_string = false;

		for ( $index = 0; $index < $length; $index++ ) {
			$char = $values_sql[ $index ];
			$next = $index + 1 < $length ? $values_sql[ $index + 1 ] : '';

			if ( $in_string ) {
				$buffer .= $char;

				if ( '\\' === $char ) {
					if ( $index + 1 < $length ) {
						$buffer .= $values_sql[ $index + 1 ];
						$index++;
					}
					continue;
				}

				if ( '\'' === $char ) {
					if ( '\'' === $next ) {
						$buffer .= $next;
						$index++;
						continue;
					}

					$in_string = false;
				}

				continue;
			}

			if ( '\'' === $char ) {
				$in_string = true;
				$buffer   .= $char;
				continue;
			}

			if ( '(' === $char ) {
				$depth++;
				if ( 1 === $depth ) {
					$buffer = '';
					continue;
				}
			}

			if ( ')' === $char ) {
				$depth--;
				if ( 0 === $depth ) {
					$groups[] = $buffer;
					$buffer   = '';
					continue;
				}
			}

			if ( $depth >= 1 ) {
				$buffer .= $char;
			}
		}

		$rows = array();

		foreach ( $groups as $group ) {
			$values = $this->parse_row_values( $group );
			if ( count( $values ) !== count( $columns ) ) {
				continue;
			}

			$row = array();
			foreach ( $columns as $column_index => $column_name ) {
				$row[ $column_name ] = $values[ $column_index ];
			}

			$rows[] = $row;
		}

		return $rows;
	}

	/**
	 * Parsea una fila individual respetando comas dentro de strings.
	 *
	 * @param string $group Grupo VALUES sin parentesis externos.
	 * @return array<int,string>
	 */
	protected function parse_row_values( $group ) {
		$tokens    = array();
		$current   = '';
		$length    = strlen( (string) $group );
		$in_string = false;

		for ( $index = 0; $index < $length; $index++ ) {
			$char = $group[ $index ];
			$next = $index + 1 < $length ? $group[ $index + 1 ] : '';

			if ( $in_string ) {
				$current .= $char;

				if ( '\\' === $char ) {
					if ( $index + 1 < $length ) {
						$current .= $group[ $index + 1 ];
						$index++;
					}
					continue;
				}

				if ( '\'' === $char ) {
					if ( '\'' === $next ) {
						$current .= $next;
						$index++;
						continue;
					}

					$in_string = false;
				}

				continue;
			}

			if ( '\'' === $char ) {
				$in_string = true;
				$current  .= $char;
				continue;
			}

			if ( ',' === $char ) {
				$tokens[] = $this->normalize_sql_value( $current );
				$current  = '';
				continue;
			}

			$current .= $char;
		}

		$tokens[] = $this->normalize_sql_value( $current );

		return $tokens;
	}

	/**
	 * Normaliza un valor SQL a string simple.
	 *
	 * @param string $value Valor crudo.
	 * @return string
	 */
	protected function normalize_sql_value( $value ) {
		$value = trim( (string) $value );

		if ( preg_match( '/^NULL$/i', $value ) ) {
			return '';
		}

		if ( preg_match( '/^\'.*\'$/s', $value ) ) {
			$value = substr( $value, 1, -1 );
			$value = str_replace( "''", "'", $value );
			$value = stripcslashes( $value );

			return (string) $value;
		}

		return trim( $value );
	}
}
