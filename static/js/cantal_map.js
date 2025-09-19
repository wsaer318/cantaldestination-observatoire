/**
 * Carte géographique interactive du Cantal - Zones d'observation touristique
 * Utilise Leaflet.js pour afficher les territoires avec données FluxVision
 */

class CantalMap {
    constructor(containerId) {
        this.containerId = containerId;
        this.map = null;
        this.zones = [];
        this.zonesLayer = null;
        
        // Configuration des 11 zones d'observation OFFICIELLES du Cantal
        // Utilise les VRAIS contours administratifs via l'API geo.api.gouv.fr
        this.zonesConfig = {
            // 1. DÉPARTEMENT DU CANTAL
            'CANTAL': {
                name: 'Département du Cantal',
                color: '#e74c3c',
                fillColor: '#e74c3c',
                opacity: 0.15,
                borderOpacity: 0.8,
                type: 'departement',
                code: '15',
                apiType: 'departement',
                description: 'Plus grand volcan d\'Europe • 246 communes • 145,000 hab',
                adminInfo: 'Département - Préfecture : Aurillac'
            },
            
            // 2. HAUTES TERRES COMMUNAUTÉ (HTC)
            'HTC': {
                name: 'Hautes Terres Communauté',
                color: '#f39c12',
                fillColor: '#f39c12',
                opacity: 0.7,
                borderOpacity: 0.9,
                type: 'epci',
                code: '200066637',
                apiType: 'epci',
                description: 'Territoire montagnard • Puy Mary Grand Site de France • 35 communes • 11,554 hab',
                adminInfo: 'Communauté de communes - Siège : Murat'
            },
            
            // 3. SAINT-FLOUR COMMUNAUTÉ
            'PAYS SAINT FLOUR': {
                name: 'Saint-Flour Communauté',
                color: '#2ecc71',
                fillColor: '#2ecc71',
                opacity: 0.7,
                borderOpacity: 0.9,
                type: 'epci',
                code: '200066660',
                apiType: 'epci',
                description: 'Plus grande intercommunalité • Planèze de Saint-Flour • 53 communes • 23,515 hab',
                adminInfo: 'Communauté de communes - Siège : Saint-Flour'
            },
            
            // 4. PAYS DE MAURIAC
            'PAYS DE MAURIAC': {
                name: 'Pays de Mauriac',
                color: '#1abc9c',
                fillColor: '#1abc9c',
                opacity: 0.7,
                borderOpacity: 0.9,
                type: 'epci',
                code: '241500271',
                apiType: 'epci',
                description: 'Sous-préfecture • Vallée de la Dordogne • 11 communes • 6,627 hab',
                adminInfo: 'Communauté de communes - Siège : Mauriac'
            },
            
            // 5. AURILLAC AGGLOMÉRATION (CABA)
            'CABA': {
                name: 'Pays d\'Aurillac',
                color: '#3498db',
                fillColor: '#3498db',
                opacity: 0.7,
                borderOpacity: 0.9,
                type: 'epci',
                code: '241500230',
                apiType: 'epci',
                description: 'Pôle urbain principal • Centre économique • 25 communes • 53,407 hab',
                adminInfo: 'Communauté d\'agglomération - Siège : Aurillac'
            },
            
            // 6. PAYS DE SALERS
            'PAYS SALERS': {
                name: 'Pays de Salers',
                color: '#9b59b6',
                fillColor: '#9b59b6',
                opacity: 0.7,
                borderOpacity: 0.9,
                type: 'epci',
                code: '241501139',
                apiType: 'epci',
                description: 'Villages de caractère • Parc Naturel Régional • 27 communes • 8,466 hab',
                adminInfo: 'Communauté de communes - Siège : Salers'
            },
            
            // 7. STATION DU LIORAN (Commune de Laveissenet)
            'STATION': {
                name: 'Station du Lioran',
                color: '#00bcd4',
                fillColor: '#00bcd4',
                opacity: 0.9,
                borderOpacity: 1.0,
                type: 'commune',
                code: '15097', // Code INSEE de Laveissenet
                apiType: 'commune',
                description: 'Plus grande station de ski du centre France • Plomb du Cantal 1,855m',
                adminInfo: 'Station de montagne - Commune : Laveissenet',
                icon: 'ski'
            },
            
            // 8. CARLADÈS
            'CARLADES': {
                name: 'Carladès',
                color: '#34495e',
                fillColor: '#34495e',
                opacity: 0.7,
                borderOpacity: 0.9,
                type: 'epci',
                code: '241501089',
                apiType: 'epci',
                description: 'Patrimoine historique • Massif Cantalien • 11 communes • 4,997 hab',
                adminInfo: 'CC Cère et Goul en Carladès - Siège : Vic-sur-Cère'
            },
            
            // 9. PAYS GENTIANE
            'GENTIANE': {
                name: 'Pays Gentiane',
                color: '#8e44ad',
                fillColor: '#8e44ad',
                opacity: 0.7,
                borderOpacity: 0.9,
                type: 'epci',
                code: '241500255',
                apiType: 'epci',
                description: 'Thermalisme • Aubrac • Festival gentiane • 17 communes • 6,720 hab',
                adminInfo: 'Communauté de communes - Siège : Riom-ès-Montagnes'
            },
            
            // 10. CHÂTAIGNERAIE CANTALIENNE
            'CHÂTAIGNERAIE': {
                name: 'Châtaigneraie Cantalienne',
                color: '#27ae60',
                fillColor: '#27ae60',
                opacity: 0.7,
                borderOpacity: 0.9,
                type: 'epci',
                code: '200066678',
                apiType: 'epci',
                description: 'Terroir châtaigne • Tradition gastronomique • 50 communes • 21,099 hab',
                adminInfo: 'Communauté de communes - Siège : Saint-Mamet-la-Salvetat'
            },
            
            // 11. VAL TRUYÈRE (territoire géographique - approcher via communes)
            'VAL TRUYÈRE': {
                name: 'Val Truyère',
                color: '#16a085',
                fillColor: '#16a085',
                opacity: 0.7,
                borderOpacity: 0.9,
                type: 'territoire',
                communes: ['15014', '15236', '15187'], // Quelques communes représentatives du Val Truyère
                apiType: 'communes',
                description: 'Vallée de la Truyère • Paysages naturels préservés • Territoire géographique',
                adminInfo: 'Territoire géographique - Vallée de la rivière Truyère'
            }
        };
        
        // Cache pour les contours géographiques
        this.geoCache = new Map();
        
        this.init();
    }
    
    async init() {
        try {
            await this.loadZonesData();
            this.createMap();
            await this.addColoredZones();
            this.setupMapControls();
        } catch (error) {
            console.error('Erreur lors de l\'initialisation de la carte:', error);
            this.showError();
        }
    }
    
    async loadZonesData() {
        try {
            // Charger les données des zones depuis l'API
            const response = await fetch('/fluxvision_fin/api/filters_mysql.php');
            const data = await response.json();
            
            if (data.zones) {
                this.zones = data.zones.filter(zone => this.zonesConfig[zone]);
                window.fvLog('Zones chargées:', this.zones);
            }
        } catch (error) {
            console.error('Erreur lors du chargement des zones:', error);
            // Utiliser les zones par défaut en cas d'erreur
            this.zones = Object.keys(this.zonesConfig);
        }
    }
    
    createMap() {
        // Initialiser la carte centrée sur le Cantal avec options responsive
        this.map = L.map(this.containerId, {
            center: [45.0, 2.7], // Centre du Cantal
            zoom: this.getInitialZoom(),
            zoomControl: true,
            scrollWheelZoom: true,
            dragging: true,
            tap: true, // Support mobile
            touchZoom: true,
            doubleClickZoom: true
        });
        
        // Ajouter le fond de carte avec meilleur contraste
        L.tileLayer('https://{s}.tile.openstreetmap.fr/osmfr/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap France | Données © OpenStreetMap contributors',
            maxZoom: 18,
            minZoom: 6
        }).addTo(this.map);
        
        // Créer le groupe de zones
        this.zonesLayer = L.layerGroup().addTo(this.map);
        
        // Gérer le redimensionnement responsive
        this.setupResponsiveMap();
    }
    
    getInitialZoom() {
        // Zoom adaptatif selon la taille d'écran
        const width = window.innerWidth;
        if (width < 768) return 8;      // Mobile
        if (width < 1024) return 9;     // Tablette  
        return 10;                      // Desktop
    }
    
    setupResponsiveMap() {
        // Réajuster la carte lors du redimensionnement
        window.addEventListener('resize', () => {
            setTimeout(() => {
                this.map.invalidateSize();
                this.map.setZoom(this.getInitialZoom());
            }, 100);
        });
    }
    
    async addColoredZones() {
        window.fvLog('🗺️ Chargement des zones administratives officielles...');
        window.fvLog('📋 Zones à charger:', this.zones);
        
        // Charger les zones une par une pour éviter de surcharger l'API
        for (const zoneName of this.zones) {
            const config = this.zonesConfig[zoneName];
            if (!config) {
                console.warn(`⚠️ Configuration manquante pour zone: ${zoneName}`);
                continue;
            }
            
            window.fvLog(`🔄 Chargement zone: ${zoneName} - ${config.name}`);
            
            try {
                let zone;
                
                if (config.apiType && config.code) {
                    window.fvLog(`🌐 API request: ${config.apiType}:${config.code} pour ${config.name}`);
                    
                    // Charger les vrais contours administratifs via l'API
                    const geoData = await this.loadAdminBoundary(config);
                    
                    if (geoData) {
                        window.fvLog(`✅ Données géo reçues pour ${config.name}:`, geoData);
                        zone = this.createGeoZone(zoneName, config, geoData);
                        window.fvLog(`✅ Zone créée pour ${config.name}:`, zone);
                    } else {
                        // Fallback si l'API échoue
                        console.warn(`⚠️ Fallback pour ${config.name} - API a échoué`);
                        zone = this.createFallbackZone(zoneName, config);
                    }
                } else if (config.communes) {
                    // Pour les territoires géographiques (Val Truyère)
                    window.fvLog(`🗺️ Territoire communes pour ${config.name}:`, config.communes);
                    const geoData = await this.loadTerritoryFromCommunes(config.communes);
                    
                    if (geoData) {
                        zone = this.createGeoZone(zoneName, config, geoData);
                    } else {
                        zone = this.createFallbackZone(zoneName, config);
                    }
                } else {
                    // Fallback si pas de configuration API
                    window.fvLog(`⚠️ Pas de config API pour ${config.name} - utilisation fallback`);
                    zone = this.createFallbackZone(zoneName, config);
                }
                
                if (zone) {
                    window.fvLog(`➕ Ajout zone ${config.name} à la carte`);
                    this.zonesLayer.addLayer(zone);
                } else {
                    console.error(`❌ Impossible de créer la zone ${config.name}`);
                }
                
                // Pause courte entre les requêtes pour éviter de surcharger l'API
                await new Promise(resolve => setTimeout(resolve, 200));
                
            } catch (error) {
                console.error(`❌ Erreur lors du chargement de ${config.name}:`, error);
                
                // Fallback en cas d'erreur
                const fallbackZone = this.createFallbackZone(zoneName, config);
                if (fallbackZone) {
                    window.fvLog(`🔄 Fallback ajouté pour ${config.name}`);
                    this.zonesLayer.addLayer(fallbackZone);
                }
            }
        }
        
        window.fvLog('✅ Toutes les zones administratives chargées');
        window.fvLog('📊 Nombre de zones sur la carte:', this.zonesLayer.getLayers().length);
    }
    
    createPolygonZone(zoneName, config) {
        // Convertir les coordonnées en format GeoJSON
        const geoJsonData = {
            type: "Feature",
            properties: {
                name: config.name
            },
            geometry: {
                type: "Polygon",
                coordinates: [config.coordinates.map(coord => [coord[0], coord[1]])]
            }
        };
        
        return this.createGeoZone(zoneName, config, geoJsonData);
    }
    
    async loadAdminBoundary(config) {
        const cacheKey = `${config.apiType}-${config.code}`;
        
        // Vérifier le cache
        if (this.geoCache.has(cacheKey)) {
            return this.geoCache.get(cacheKey);
        }
        
        let apiUrl;
        
        try {
            // Cas spécial pour le département du Cantal - utiliser une source alternative
            if (config.apiType === 'departement' && config.code === '15') {
                window.fvLog(`🔄 Utilisation de la source alternative pour le département du Cantal`);
                
                // Utiliser le projet france-geojson qui fournit les contours des départements
                apiUrl = 'https://raw.githubusercontent.com/gregoiredavid/france-geojson/master/departements-avec-outre-mer.geojson';
                
                const response = await fetch(apiUrl);
                if (!response.ok) {
                    console.error(`❌ Erreur source alternative (${response.status}): ${response.statusText}`);
                    throw new Error(`HTTP ${response.status}`);
                }
                
                const allDepartments = await response.json();
                
                // Trouver le département du Cantal (code "15")
                const cantalFeature = allDepartments.features.find(feature => 
                    feature.properties.code === "15" || 
                    feature.properties.code === 15 ||
                    feature.properties.nom === "Cantal"
                );
                
                if (cantalFeature) {
                    // Mettre en cache seulement la feature du Cantal
                    this.geoCache.set(cacheKey, cantalFeature);
                    
                    window.fvLog(`✅ Contour du département du Cantal trouvé dans source alternative`);
                    
                    return cantalFeature;
                } else {
                    console.error(`❌ Département du Cantal non trouvé dans la source alternative`);
                    throw new Error('Département du Cantal non trouvé');
                }
            }
            
            switch (config.apiType) {
                case 'departement':
                    apiUrl = `https://geo.api.gouv.fr/departements/${config.code}?geometry=contour&format=geojson`;
                    break;
                case 'epci':
                    apiUrl = `https://geo.api.gouv.fr/epcis/${config.code}?geometry=contour&format=geojson`;
                    break;
                case 'commune':
                    apiUrl = `https://geo.api.gouv.fr/communes/${config.code}?geometry=contour&format=geojson`;
                    break;
                case 'communes':
                    // Pour les territoires géographiques (comme Val Truyère)
                    return await this.loadTerritoryFromCommunes(config.communes);
                default:
                    throw new Error('Type administratif non supporté');
            }
            
            window.fvLog(`🌍 Chargement contour administratif : ${config.name} (${config.apiType}:${config.code})`);
            
            const response = await fetch(apiUrl);
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }
            
            const geoData = await response.json();
            
            // Mettre en cache
            this.geoCache.set(cacheKey, geoData);
            
            window.fvLog(`✅ Contour chargé : ${config.name}`);
            
            return geoData;
            
        } catch (error) {
            console.warn(`❌ Erreur API pour ${config.name}:`, error);
            return null;
        }
    }
    
    async loadTerritoryFromCommunes(communeCodes) {
        if (!communeCodes || communeCodes.length === 0) return null;
        
        try {
            // Pour les territoires géographiques, charger toutes les communes et les fusionner
            const promises = communeCodes.map(code => 
                fetch(`https://geo.api.gouv.fr/communes/${code}?geometry=contour&format=geojson`)
                    .then(response => response.ok ? response.json() : null)
            );
            
            const communesData = await Promise.all(promises);
            const validCommunes = communesData.filter(data => data !== null);
            
            if (validCommunes.length === 0) throw new Error('Aucune commune valide');
            
            // Si une seule commune, la retourner directement
            if (validCommunes.length === 1) {
                return validCommunes[0];
            }
            
            // Sinon, créer une collection de features
            return {
                type: "FeatureCollection",
                features: validCommunes.map(commune => commune.features ? commune.features[0] : commune)
            };
            
        } catch (error) {
            console.warn(`Erreur territoire communes ${communeCodes}:`, error);
            return null;
        }
    }
    
    createGeoZone(zoneName, config, geoData) {
        // Créer la zone à partir des données GeoJSON
        const zone = L.geoJSON(geoData, {
            style: {
                fillColor: config.fillColor,
                color: config.color,
                weight: config.type === 'department' ? 3 : 2,
                opacity: config.borderOpacity,
                fillOpacity: config.opacity,
                dashArray: config.type === 'department' ? '8, 8' : null
            }
        });
        
        // Ajouter les interactions
        zone.bindPopup(this.createPopupContent(zoneName, config));
        zone.bindTooltip(config.name, {
            permanent: false,
            direction: 'center',
            className: 'zone-tooltip'
        });
        
        // Événements hover
        zone.on('mouseover', (e) => {
            const layer = e.target;
            layer.setStyle({
                weight: (layer.options.weight || 2) + 2,
                fillOpacity: Math.min((layer.options.fillOpacity || 0.4) + 0.2, 0.8)
            });
        });
        
        zone.on('mouseout', (e) => {
            const layer = e.target;
            layer.setStyle({
                weight: config.type === 'department' ? 3 : 2,
                fillOpacity: config.opacity
            });
        });
        
        // Icône spéciale pour la station de ski
        if (config.icon === 'ski') {
            const bounds = zone.getBounds();
            const center = bounds.getCenter();
            
            const skiIcon = L.divIcon({
                className: 'ski-zone-icon',
                html: '<i class="fas fa-skiing" style="color: white; font-size: 18px; text-shadow: 2px 2px 4px rgba(0,0,0,0.8);"></i>',
                iconSize: [24, 24],
                iconAnchor: [12, 12]
            });
            
            const skiMarker = L.marker(center, { icon: skiIcon });
            this.zonesLayer.addLayer(skiMarker);
        }
        
        return zone;
    }
    
    createFallbackZone(zoneName, config) {
        // Zone de fallback (cercle) si les contours ne sont pas disponibles
        const center = this.getApproximateCenter(zoneName);
        const radius = this.getApproximateRadius(config.type);
        
        const zone = L.circle(center, {
            radius: radius,
            fillColor: config.fillColor,
            color: config.color,
            weight: 2,
            opacity: config.borderOpacity,
            fillOpacity: config.opacity * 0.7, // Opacity réduite pour indiquer que c'est approximatif
            dashArray: '4, 8' // Style pointillé pour indiquer l'approximation
        });
        
        zone.bindPopup(this.createPopupContent(zoneName, config));
        zone.bindTooltip(`${config.name} (approximatif)`, {
            permanent: false,
            direction: 'center',
            className: 'zone-tooltip'
        });
        
        return zone;
    }
    
    getApproximateCenter(zoneName) {
        const centers = {
            'CANTAL': [44.9317, 2.4444],
            'CABA': [44.9262, 2.4444],
            'PAYS SAINT FLOUR': [45.0337, 3.0964],
            'HTC': [45.2167, 2.8667],
            'PAYS SALERS': [45.1406, 2.4947],
            'PAYS DE MAURIAC': [45.2167, 2.3333],
            'CCSA': [45.4167, 2.4167],
            'CARLADES': [44.8833, 2.9167],
            'GENTIANE': [45.0833, 2.9500],
            'STATION': [45.0844, 2.7508]
        };
        return centers[zoneName] || [44.9317, 2.4444];
    }
    
    getApproximateRadius(type) {
        const radii = {
            'department': 25000,
            'epci': 10000,
            'territory': 8000,
            'commune': 3000
        };
        return radii[type] || 8000;
    }
    

    
    createPopupContent(zoneName, config) {
        const adminIcon = this.getAdminIcon(config.apiType);
        const adminBadge = config.apiType ? 
            `<span style="background: rgba(${config.color.replace('#', '').match(/.{2}/g).map(x => parseInt(x, 16)).join(', ')}, 0.1); 
                         color: ${config.color}; padding: 2px 6px; border-radius: 3px; font-size: 10px; margin-left: 8px;">
                ${adminIcon} ${this.getAdminTypeLabel(config.apiType)}
             </span>` : '';
        
        return `
            <div class="zone-popup">
                <h4 style="margin: 0 0 8px 0; color: ${config.color}; font-size: 16px; display: flex; align-items: center;">
                    ${config.name}
                    ${adminBadge}
                </h4>
                <p style="margin: 0 0 8px 0; font-size: 13px; color: #666;">
                    ${config.description}
                </p>
                ${config.adminInfo ? 
                    `<p style="margin: 0 0 10px 0; font-size: 11px; color: #888; font-style: italic;">
                        📍 ${config.adminInfo}
                    </p>` : ''
                }
                <div style="text-align: center; margin-top: 10px;">
                    <a href="/fluxvision_fin/tdb_comparaison?zone=${encodeURIComponent(zoneName)}" 
                       class="popup-btn" 
                       style="
                           background: ${config.color};
                           color: white;
                           padding: 6px 12px;
                           border-radius: 4px;
                           text-decoration: none;
                           font-size: 12px;
                           font-weight: bold;
                           display: inline-block;
                           transition: opacity 0.3s;
                       "
                       onmouseover="this.style.opacity='0.8'"
                       onmouseout="this.style.opacity='1'">
                        <i class="fas fa-chart-bar"></i> Voir les données
                    </a>
                </div>
            </div>
        `;
    }
    
    getAdminIcon(apiType) {
        const icons = {
            'departement': '🏛️',
            'epci': '🏘️',
            'commune': '🏠',
            'communes': '🗺️'
        };
        return icons[apiType] || '📍';
    }
    
    getAdminTypeLabel(apiType) {
        const labels = {
            'departement': 'Département',
            'epci': 'EPCI',
            'commune': 'Commune',
            'communes': 'Territoire'
        };
        return labels[apiType] || 'Zone';
    }
    
    setupMapControls() {
        // Ajouter un contrôle de légende
        const legend = L.control({ position: 'bottomright' });
        
        legend.onAdd = () => {
            const div = L.DomUtil.create('div', 'map-legend');
            div.style.cssText = `
                background: rgba(255, 255, 255, 0.95);
                padding: 10px;
                border-radius: 8px;
                box-shadow: 0 2px 8px rgba(0,0,0,0.2);
                font-size: 12px;
                max-width: 200px;
            `;
            
            let html = '<h5 style="margin: 0 0 8px 0; font-size: 14px;"><i class="fas fa-map-marker-alt"></i> Zones d\'observation</h5>';
            
            // Types administratifs avec exemples représentatifs
            const adminTypes = [
                {
                    icon: '🏛️',
                    label: 'Département',
                    example: 'Cantal',
                    color: '#e74c3c'
                },
                {
                    icon: '🏘️',
                    label: 'Communautés',
                    example: 'EPCI & CC',
                    color: '#3498db'
                },
                {
                    icon: '🏠',
                    label: 'Station ski',
                    example: 'Lioran',
                    color: '#00bcd4'
                },
                {
                    icon: '🗺️',
                    label: 'Territoire',
                    example: 'Val Truyère',
                    color: '#16a085'
                }
            ];
            
            adminTypes.forEach(({ icon, label, example, color }) => {
                html += `
                    <div style="display: flex; align-items: center; margin: 4px 0; font-size: 11px;">
                        <span style="
                            font-size: 14px;
                            margin-right: 6px;
                            width: 16px;
                            text-align: center;
                        ">${icon}</span>
                        <div style="
                            width: 8px;
                            height: 8px;
                            background: ${color};
                            border-radius: 2px;
                            margin-right: 6px;
                            border: 1px solid rgba(0,0,0,0.2);
                        "></div>
                        <span style="font-weight: 500;">${label}</span>
                        <span style="color: #666; margin-left: 4px; font-size: 10px;">${example}</span>
                    </div>
                `;
            });
            
            // Note explicative
            html += `
                <div style="
                    margin-top: 8px; 
                    padding-top: 6px; 
                    border-top: 1px solid #eee; 
                    font-size: 10px; 
                    color: #888;
                    line-height: 1.2;
                ">
                    <i class="fas fa-info-circle" style="margin-right: 4px;"></i>
                    Contours administratifs officiels
                </div>
            `;
            
            div.innerHTML = html;
            return div;
        };
        
        legend.addTo(this.map);
    }
    
    showError() {
        const container = document.getElementById(this.containerId);
        if (container) {
            container.innerHTML = `
                <div style="
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    height: 400px;
                    background: #f8f9fa;
                    border-radius: 8px;
                    color: #666;
                    font-size: 16px;
                ">
                    <i class="fas fa-exclamation-triangle" style="margin-right: 8px;"></i>
                    Erreur lors du chargement de la carte
                </div>
            `;
        }
    }
}

// Auto-initialisation quand la page est chargée
document.addEventListener('DOMContentLoaded', () => {
    const mapContainer = document.getElementById('cantal-map');
    if (mapContainer) {
        new CantalMap('cantal-map');
    }
});