                                                                    
_|                            _|            _|        _|            
_|    _|_|    _|_|_|      _|_|_|    _|_|_|  _|_|_|    _|    _|_|    
_|  _|_|_|_|  _|    _|  _|    _|  _|    _|  _|    _|  _|  _|_|_|_|  
_|  _|        _|    _|  _|    _|  _|    _|  _|    _|  _|  _|        
_|    _|_|_|  _|    _|    _|_|_|    _|_|_|  _|_|_|    _|    _|_|_|  


INSTALL
=======

To bootstrap PHP dependencies:

    $ curl -sS https://getcomposer.org/installer | php
    $ ./composer install

Install other dependencies in one go:

	$ make install-deps

Allow apache to write to the logs:

	$ mkdir -p logs cache db
	$ chmod 777 logs cache db