#	 _|                            _|            _|        _|            
#	 _|    _|_|    _|_|_|      _|_|_|    _|_|_|  _|_|_|    _|    _|_|    
#	 _|  _|_|_|_|  _|    _|  _|    _|  _|    _|  _|    _|  _|  _|_|_|_|  
#	 _|  _|        _|    _|  _|    _|  _|    _|  _|    _|  _|  _|        
#	 _|    _|_|_|  _|    _|    _|_|_|    _|_|_|  _|_|_|    _|    _|_|_|  
#
# Install non-php dependencies
# 
FN_VERSION = 4.1.6
OL_VERSION = 2.12
ASSETS_DIR = assets

FOUNDATION_URL = http://foundation.zurb.com/files/foundation-$(FN_VERSION).zip
OPENLAYERS_URL = http://openlayers.org/download/OpenLayers-$(OL_VERSION).tar.gz

help:
	@cat INSTALL.md

assets:
	mkdir -p assets

install-deps: $(ASSETS_DIR)/foundation-$(FN_VERSION) $(ASSETS_DIR)/OpenLayers-$(OL_VERSION) \
	$(ASSETS_DIR)/Toast $(ASSETS_DIR)/images/8623939313_958592edc2_b_d.jpg
	cd $(ASSETS_DIR) && rm -f foundation OpenLayers
	cd $(ASSETS_DIR) && ln -s foundation-$(FN_VERSION) foundation
	cd $(ASSETS_DIR) && ln -s OpenLayers-$(OL_VERSION) OpenLayers

$(ASSETS_DIR)/foundation-$(FN_VERSION): $(ASSETS_DIR)/foundation-$(FN_VERSION).zip
	cd $(ASSETS_DIR) && mkdir -p foundation-$(FN_VERSION) && \
		unzip -qo foundation-$(FN_VERSION).zip -d foundation-$(FN_VERSION)

$(ASSETS_DIR)/foundation-$(FN_VERSION).zip: $(ASSETS_DIR)
	cd $(ASSETS_DIR) && wget -q -nc $(FOUNDATION_URL)

$(ASSETS_DIR)/OpenLayers-$(OL_VERSION): $(ASSETS_DIR)/OpenLayers-$(OL_VERSION).tar.gz
	cd $(ASSETS_DIR) && tar xfz OpenLayers-$(OL_VERSION).tar.gz

$(ASSETS_DIR)/OpenLayers-$(OL_VERSION).tar.gz: $(ASSETS_DIR)
	cd $(ASSETS_DIR) && wget -q -nc $(OPENLAYERS_URL)

$(ASSETS_DIR)/Toast:
	cd $(ASSETS_DIR) && git clone git://github.com/daneden/Toast.git

$(ASSETS_DIR)/images/8623939313_958592edc2_b_d.jpg:
	cd $(ASSETS_DIR) && wget -q -nc http://farm9.staticflickr.com/8389/8623939313_958592edc2_b_d.jpg