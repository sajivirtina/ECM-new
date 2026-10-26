(function () {
  'use strict';

  // Curated landmark photography for the supported metro areas. Nearby cities
  // use their metro's identifying landmark rather than a random street view.
  function unsplashImage(id) {
    return 'https://images.unsplash.com/' + id + '?auto=format&fit=crop&w=1200&q=85';
  }

  function commonsImage(filename, width) {
    return 'https://commons.wikimedia.org/wiki/Special:Redirect/file/'
      + encodeURIComponent(filename)
      + '?width=' + (width || 1200);
  }

  var METRO_LANDMARKS = [
    [32.7767, -96.7970, unsplashImage('photo-1517245386807-bb43f82c33c4')], // Dallas
    [30.2672, -97.7431, unsplashImage('photo-1531219572328-a0171b4448a3')], // Austin
    [29.7604, -95.3698, unsplashImage('photo-1531058020387-3be344556be6')], // Houston
    [29.4241, -98.4936, unsplashImage('photo-1531219572328-a0171b4448a3')], // San Antonio
    [39.9612, -82.9988, unsplashImage('photo-1514924013411-cbf25faa35bb')], // Columbus
    [41.4993, -81.6944, unsplashImage('photo-1514924013411-cbf25faa35bb')], // Cleveland
    [39.1031, -84.5120, unsplashImage('photo-1514924013411-cbf25faa35bb')], // Cincinnati
    [25.7617, -80.1918, unsplashImage('photo-1506968430777-bf7784a87f23')], // Miami
    [28.5383, -81.3792, unsplashImage('photo-1514214246283-d427a95c5d2f')], // Orlando
    [27.9506, -82.4572, unsplashImage('photo-1561063139-09996cdf3f49')], // Tampa Bay
    [34.0522, -118.2437, unsplashImage('photo-1444723121867-7a241cacace9')], // Los Angeles
    [37.7749, -122.4194, unsplashImage('photo-1501466044931-62695aada8e9')], // San Francisco
    [32.7157, -117.1611, unsplashImage('photo-1603725117928-6f09697f34aa')], // San Diego
    [37.3382, -121.8863, unsplashImage('photo-1501466044931-62695aada8e9')], // San Jose
    [40.7128, -74.0060, unsplashImage('photo-1522083165195-3424ed129620')], // New York City
    [42.8864, -78.8784, unsplashImage('photo-1522083165195-3424ed129620')], // Buffalo
    [41.8781, -87.6298, unsplashImage('photo-1477959858617-67f85cf4f1df')], // Chicago
    [33.7490, -84.3880, commonsImage('Atlanta, Georgia Skyline.jpg')], // Atlanta
    [32.0835, -81.0998, unsplashImage('photo-1514214246283-d427a95c5d2f')], // Savannah
    [35.2271, -80.8431, unsplashImage('photo-1514924013411-cbf25faa35bb')], // Charlotte
    [35.7796, -78.6382, unsplashImage('photo-1509316785289-025f5b846b35')], // Raleigh
    [33.4484, -112.0740, commonsImage('Phoenix skyline Arizona USA.jpg')], // Phoenix
    [47.6062, -122.3321, unsplashImage('photo-1502175353174-a7a70e73b362')], // Seattle
    [39.7392, -104.9903, unsplashImage('photo-1509316785289-025f5b846b35')], // Denver
    [42.3314, -83.0458, unsplashImage('photo-1522083165195-3424ed129620')], // Detroit
    [36.8529, -75.9780, unsplashImage('photo-1507525428034-b723cf961d3e')], // Virginia Beach
    [37.5407, -77.4360, unsplashImage('photo-1509316785289-025f5b846b35')], // Richmond
    [36.1627, -86.7816, unsplashImage('photo-1693433583226-9acd8acad2e4')], // Nashville
    [35.1495, -90.0490, unsplashImage('photo-1545419913-775c3906b2cc')], // Memphis
    [39.2904, -76.6122, unsplashImage('photo-1559339352-11d035aa65de')], // Baltimore
    [38.9784, -76.4922, unsplashImage('photo-1559339352-11d035aa65de')], // Annapolis
    [42.3601, -71.0589, unsplashImage('photo-1501979376754-2ff867a4f659')], // Boston
    [39.9526, -75.1652, commonsImage('Philadelphia skyline panorama.jpg')], // Philadelphia
    [40.4406, -79.9959, commonsImage('Pittsburgh Skyline.JPG')], // Pittsburgh
    [40.2731, -76.8867, commonsImage('Pennsylvania State Capitol.jpg')] // Harrisburg
  ];

  // Keeps the location records compact while replacing the former Street View API.
  function gsv(lat, lon) {
    var closest = METRO_LANDMARKS[0];
    var closestDistance = Infinity;

    METRO_LANDMARKS.forEach(function (landmark) {
      var latitudeDistance = landmark[0] - lat;
      var longitudeDistance = (landmark[1] - lon) * Math.cos(lat * Math.PI / 180);
      var distance = latitudeDistance * latitudeDistance + longitudeDistance * longitudeDistance;

      if (distance < closestDistance) {
        closest = landmark;
        closestDistance = distance;
      }
    });

    return closest[2];
  }

  const CITIES = {
    // ── Texas ──────────────────────────────────────────────────────────────────
    'dallas':          { key: 'dallas',          name: 'Dallas',          state: 'TX', lat: 32.7767,  lon: -96.7970,  nearby: ['fort-worth','plano','arlington'],           image: gsv(32.7767,  -96.7970,  180, 5, 80), tagline: 'The Dallas–Fort Worth Metroplex is home to 500+ licensed elder care providers.' },
    'fort-worth':      { key: 'fort-worth',      name: 'Fort Worth',      state: 'TX', lat: 32.7555,  lon: -97.3308,  nearby: ['dallas','arlington','plano'],                image: gsv(32.7555,  -97.3308,  200, 5, 80), tagline: 'Fort Worth blends Texas heritage with a growing network of senior care services.' },
    'plano':           { key: 'plano',           name: 'Plano',           state: 'TX', lat: 33.0198,  lon: -96.6989,  nearby: ['dallas','fort-worth','arlington'],           image: gsv(33.0198,  -96.6989,  90,  5, 80), tagline: 'Plano is consistently ranked among the best Texas cities for senior living.' },
    'arlington':       { key: 'arlington',       name: 'Arlington',       state: 'TX', lat: 32.7357,  lon: -97.1081,  nearby: ['fort-worth','dallas','plano'],               image: gsv(32.7357,  -97.1081,  270, 5, 80), tagline: 'Arlington sits at the heart of the Metroplex with dedicated memory care communities.' },
    'austin':          { key: 'austin',          name: 'Austin',          state: 'TX', lat: 30.2672,  lon: -97.7431,  nearby: ['round-rock','cedar-park'],                   image: gsv(30.2672,  -97.7431,  90,  5, 80), tagline: 'Austin’s senior care scene has grown 40% in the last five years.' },
    'houston':         { key: 'houston',         name: 'Houston',         state: 'TX', lat: 29.7604,  lon: -95.3698,  nearby: ['sugar-land','katy'],                         image: gsv(29.7604,  -95.3698,  180, 5, 80), tagline: 'Houston has the largest concentration of hospice and palliative care providers in Texas.' },
    'san-antonio':     { key: 'san-antonio',     name: 'San Antonio',     state: 'TX', lat: 29.4241,  lon: -98.4936,  nearby: ['austin','houston'],                          image: gsv(29.4241,  -98.4936,  160, 5, 80), tagline: 'San Antonio’s large senior population is served by a robust elder care network.' },
    'round-rock':      { key: 'round-rock',      name: 'Round Rock',      state: 'TX', lat: 30.5083,  lon: -97.6789,  nearby: ['austin','cedar-park'],                       image: gsv(30.5083,  -97.6789,  90,  5, 80), tagline: 'Round Rock is a fast-growing community with expanding elder care resources.' },
    'cedar-park':      { key: 'cedar-park',      name: 'Cedar Park',      state: 'TX', lat: 30.5052,  lon: -97.8203,  nearby: ['austin','round-rock'],                       image: gsv(30.5052,  -97.8203,  270, 5, 80), tagline: 'Cedar Park offers a mix of home care and assisted living options for families.' },
    'sugar-land':      { key: 'sugar-land',      name: 'Sugar Land',      state: 'TX', lat: 29.6197,  lon: -95.6349,  nearby: ['houston','katy'],                            image: gsv(29.6197,  -95.6349,  180, 5, 80), tagline: 'Sugar Land has highly rated senior care communities with diverse provider options.' },
    'katy':            { key: 'katy',            name: 'Katy',            state: 'TX', lat: 29.7858,  lon: -95.8244,  nearby: ['houston','sugar-land'],                      image: gsv(29.7858,  -95.8244,  90,  5, 80), tagline: 'Katy’s rapidly growing population includes a strong elder care service sector.' },

    // ── Ohio ───────────────────────────────────────────────────────────────────
    'ohio':            { key: 'ohio',            name: 'Ohio',            state: 'OH', lat: 39.9612,  lon: -82.9988,  nearby: ['columbus','cleveland','cincinnati'],         image: gsv(39.9612,  -82.9988,  180, 5, 80), tagline: 'Ohio has one of the largest networks of licensed elder care providers in the Midwest.' },
    'columbus':        { key: 'columbus',        name: 'Columbus',        state: 'OH', lat: 39.9612,  lon: -82.9988,  nearby: ['ohio','cleveland','cincinnati'],             image: gsv(39.9612,  -82.9988,  90,  5, 80), tagline: 'Columbus is home to a growing network of certified home care and assisted living providers.' },
    'cleveland':       { key: 'cleveland',       name: 'Cleveland',       state: 'OH', lat: 41.4993,  lon: -81.6944,  nearby: ['columbus','akron','cincinnati'],             image: gsv(41.4993,  -81.6944,  180, 5, 80), tagline: 'Cleveland’s world-class medical institutions anchor a strong elder care ecosystem.' },
    'cincinnati':      { key: 'cincinnati',      name: 'Cincinnati',      state: 'OH', lat: 39.1031,  lon: -84.5120,  nearby: ['columbus','dayton','cleveland'],             image: gsv(39.1031,  -84.5120,  270, 5, 80), tagline: 'Cincinnati offers a broad range of elder care services across its neighborhoods.' },
    'akron':           { key: 'akron',           name: 'Akron',           state: 'OH', lat: 41.0814,  lon: -81.5190,  nearby: ['cleveland','canton'],                        image: gsv(41.0814,  -81.5190,  90,  5, 80), tagline: 'Akron’s senior care community continues to grow with strong home care networks.' },
    'dayton':          { key: 'dayton',          name: 'Dayton',          state: 'OH', lat: 39.7589,  lon: -84.1916,  nearby: ['cincinnati','columbus'],                     image: gsv(39.7589,  -84.1916,  180, 5, 80), tagline: 'Dayton provides families with a wide range of senior living and home care choices.' },
    'toledo':          { key: 'toledo',          name: 'Toledo',          state: 'OH', lat: 41.6528,  lon: -83.5379,  nearby: ['columbus','cleveland'],                      image: gsv(41.6528,  -83.5379,  270, 5, 80), tagline: 'Toledo is well served by home care agencies and assisted living communities.' },
    'canton':          { key: 'canton',          name: 'Canton',          state: 'OH', lat: 40.7989,  lon: -81.3784,  nearby: ['akron','cleveland'],                         image: gsv(40.7989,  -81.3784,  90,  5, 80), tagline: 'Canton families benefit from a close-knit network of senior care providers.' },

    // ── Pennsylvania ───────────────────────────────────────────────────────────
    'pennsylvania':    { key: 'pennsylvania',    name: 'Pennsylvania',    state: 'PA', lat: 40.2731,  lon: -76.8867,  nearby: ['philadelphia','pittsburgh'],                 image: gsv(40.2731,  -76.8867,  180, 5, 80), tagline: 'Pennsylvania has a rich network of elder care providers across urban and rural areas.' },
    'philadelphia':    { key: 'philadelphia',    name: 'Philadelphia',    state: 'PA', lat: 39.9526,  lon: -75.1652,  nearby: ['pennsylvania','pittsburgh','allentown'],     image: gsv(39.9526,  -75.1652,  90,  5, 80), tagline: 'Philadelphia’s diverse elder care sector includes home care, memory care, and legal services.' },
    'pittsburgh':      { key: 'pittsburgh',      name: 'Pittsburgh',      state: 'PA', lat: 40.4406,  lon: -79.9959,  nearby: ['pennsylvania','philadelphia'],               image: gsv(40.4406,  -79.9959,  200, 5, 80), tagline: 'Pittsburgh families have access to a wide range of trusted elder care providers.' },
    'allentown':       { key: 'allentown',       name: 'Allentown',       state: 'PA', lat: 40.6023,  lon: -75.4714,  nearby: ['philadelphia','pennsylvania'],               image: gsv(40.6023,  -75.4714,  180, 5, 80), tagline: 'Allentown’s senior care community is growing to meet the needs of an aging population.' },

    // ── Florida ────────────────────────────────────────────────────────────────
    'florida':         { key: 'florida',         name: 'Florida',         state: 'FL', lat: 27.9944,  lon: -81.7603,  nearby: ['orlando','miami','tampa'],                   image: gsv(27.9944,  -81.7603,  180, 5, 80), tagline: 'Florida leads the nation in elder care options for a diverse senior population.' },
    'miami':           { key: 'miami',           name: 'Miami',           state: 'FL', lat: 25.7617,  lon: -80.1918,  nearby: ['florida','fort-lauderdale','boca-raton'],    image: gsv(25.7617,  -80.1918,  90,  5, 80), tagline: 'Miami’s multicultural elder care community offers bilingual services and diverse options.' },
    'orlando':         { key: 'orlando',         name: 'Orlando',         state: 'FL', lat: 28.5383,  lon: -81.3792,  nearby: ['florida','tampa','jacksonville'],            image: gsv(28.5383,  -81.3792,  180, 5, 80), tagline: 'Orlando has a rapidly expanding network of senior living and in-home care services.' },
    'tampa':           { key: 'tampa',           name: 'Tampa',           state: 'FL', lat: 27.9506,  lon: -82.4572,  nearby: ['florida','orlando','sarasota'],              image: gsv(27.9506,  -82.4572,  270, 5, 80), tagline: 'Tampa Bay is one of Florida’s top retirement destinations with excellent elder care options.' },
    'jacksonville':    { key: 'jacksonville',    name: 'Jacksonville',    state: 'FL', lat: 30.3322,  lon: -81.6557,  nearby: ['florida','orlando'],                         image: gsv(30.3322,  -81.6557,  180, 5, 80), tagline: 'Jacksonville offers families a wide range of elder care providers across its large metro area.' },
    'sarasota':        { key: 'sarasota',        name: 'Sarasota',        state: 'FL', lat: 27.3364,  lon: -82.5307,  nearby: ['tampa','florida'],                           image: gsv(27.3364,  -82.5307,  90,  5, 80), tagline: 'Sarasota is consistently ranked among the best Florida cities for senior living.' },
    'fort-lauderdale': { key: 'fort-lauderdale', name: 'Fort Lauderdale', state: 'FL', lat: 26.1224,  lon: -80.1373,  nearby: ['miami','boca-raton'],                        image: gsv(26.1224,  -80.1373,  180, 5, 80), tagline: 'Fort Lauderdale’s elder care market includes premium assisted living and memory care.' },
    'boca-raton':      { key: 'boca-raton',      name: 'Boca Raton',      state: 'FL', lat: 26.3683,  lon: -80.1289,  nearby: ['fort-lauderdale','miami'],                   image: gsv(26.3683,  -80.1289,  90,  5, 80), tagline: 'Boca Raton is home to many upscale senior living communities and elder care specialists.' },

    // ── California ─────────────────────────────────────────────────────────────
    'california':      { key: 'california',      name: 'California',      state: 'CA', lat: 36.7783,  lon: -119.4179, nearby: ['los-angeles','san-francisco','san-diego'],   image: gsv(36.7783,  -119.4179, 180, 5, 80), tagline: 'California offers the widest range of elder care services of any US state.' },
    'los-angeles':     { key: 'los-angeles',     name: 'Los Angeles',     state: 'CA', lat: 34.0522,  lon: -118.2437, nearby: ['california','san-diego','long-beach'],       image: gsv(34.0522,  -118.2437, 90,  5, 80), tagline: 'Los Angeles has a diverse and extensive elder care network across all neighborhoods.' },
    'san-francisco':   { key: 'san-francisco',   name: 'San Francisco',   state: 'CA', lat: 37.7749,  lon: -122.4194, nearby: ['california','san-jose','oakland'],           image: gsv(37.7749,  -122.4194, 180, 5, 80), tagline: 'San Francisco’s elder care options reflect its commitment to quality senior living.' },
    'san-diego':       { key: 'san-diego',       name: 'San Diego',       state: 'CA', lat: 32.7157,  lon: -117.1611, nearby: ['california','los-angeles'],                  image: gsv(32.7157,  -117.1611, 270, 5, 80), tagline: 'San Diego’s year-round climate makes it an ideal location for senior care services.' },
    'san-jose':        { key: 'san-jose',        name: 'San Jose',        state: 'CA', lat: 37.3382,  lon: -121.8863, nearby: ['san-francisco','california','oakland'],       image: gsv(37.3382,  -121.8863, 90,  5, 80), tagline: 'San Jose offers a growing range of high-quality elder care and home care services.' },
    'long-beach':      { key: 'long-beach',      name: 'Long Beach',      state: 'CA', lat: 33.7701,  lon: -118.1937, nearby: ['los-angeles','california'],                  image: gsv(33.7701,  -118.1937, 180, 5, 80), tagline: 'Long Beach provides coastal senior living options with diverse care providers.' },
    'oakland':         { key: 'oakland',         name: 'Oakland',         state: 'CA', lat: 37.8044,  lon: -122.2712, nearby: ['san-francisco','san-jose'],                  image: gsv(37.8044,  -122.2712, 270, 5, 80), tagline: 'Oakland’s elder care community serves a diverse and vibrant senior population.' },

    // ── New York ───────────────────────────────────────────────────────────────
    'new-york':        { key: 'new-york',        name: 'New York',        state: 'NY', lat: 40.7128,  lon: -74.0060,  nearby: ['brooklyn','queens','bronx'],                 image: gsv(40.7128,  -74.0060,  90,  5, 80), tagline: 'New York City has the nation’s most comprehensive network of elder care providers.' },
    'brooklyn':        { key: 'brooklyn',        name: 'Brooklyn',        state: 'NY', lat: 40.6782,  lon: -73.9442,  nearby: ['new-york','queens'],                         image: gsv(40.6782,  -73.9442,  180, 5, 80), tagline: 'Brooklyn’s diverse neighborhoods are home to many community-based elder care services.' },
    'queens':          { key: 'queens',          name: 'Queens',          state: 'NY', lat: 40.7282,  lon: -73.7949,  nearby: ['new-york','brooklyn'],                       image: gsv(40.7282,  -73.7949,  270, 5, 80), tagline: 'Queens offers multilingual elder care services reflecting its diverse community.' },
    'bronx':           { key: 'bronx',           name: 'Bronx',           state: 'NY', lat: 40.8448,  lon: -73.8648,  nearby: ['new-york','queens'],                         image: gsv(40.8448,  -73.8648,  90,  5, 80), tagline: 'The Bronx has expanded its elder care resources to serve a growing senior population.' },
    'buffalo':         { key: 'buffalo',         name: 'Buffalo',         state: 'NY', lat: 42.8864,  lon: -78.8784,  nearby: ['new-york','rochester'],                      image: gsv(42.8864,  -78.8784,  180, 5, 80), tagline: 'Buffalo’s elder care community is anchored by strong non-profit and faith-based providers.' },
    'rochester':       { key: 'rochester',       name: 'Rochester',       state: 'NY', lat: 43.1566,  lon: -77.6088,  nearby: ['buffalo','new-york'],                        image: gsv(43.1566,  -77.6088,  90,  5, 80), tagline: 'Rochester has a well-developed senior care network with excellent home care options.' },

    // ── Illinois ───────────────────────────────────────────────────────────────
    'chicago':         { key: 'chicago',         name: 'Chicago',         state: 'IL', lat: 41.8781,  lon: -87.6298,  nearby: ['evanston','naperville','aurora'],            image: gsv(41.8781,  -87.6298,  90,  5, 80), tagline: 'Chicago’s elder care sector spans world-class medical centers and community-based care.' },
    'evanston':        { key: 'evanston',        name: 'Evanston',        state: 'IL', lat: 42.0450,  lon: -87.6877,  nearby: ['chicago','naperville'],                      image: gsv(42.0450,  -87.6877,  180, 5, 80), tagline: 'Evanston offers a strong community of senior care providers near Chicago.' },
    'naperville':      { key: 'naperville',      name: 'Naperville',      state: 'IL', lat: 41.7508,  lon: -88.1535,  nearby: ['chicago','aurora'],                          image: gsv(41.7508,  -88.1535,  270, 5, 80), tagline: 'Naperville consistently ranks among the best Midwest cities for senior living.' },
    'aurora':          { key: 'aurora',          name: 'Aurora',          state: 'IL', lat: 41.7606,  lon: -88.3201,  nearby: ['naperville','chicago'],                      image: gsv(41.7606,  -88.3201,  90,  5, 80), tagline: 'Aurora offers diverse and affordable elder care options in the greater Chicago area.' },

    // ── Georgia ────────────────────────────────────────────────────────────────
    'atlanta':         { key: 'atlanta',         name: 'Atlanta',         state: 'GA', lat: 33.7490,  lon: -84.3880,  nearby: ['savannah','marietta'],                       image: gsv(33.7490,  -84.3880,  180, 5, 80), tagline: 'Atlanta’s booming metro area includes a wide range of elder care services.' },
    'savannah':        { key: 'savannah',        name: 'Savannah',        state: 'GA', lat: 32.0835,  lon: -81.0998,  nearby: ['atlanta','marietta'],                        image: gsv(32.0835,  -81.0998,  90,  5, 80), tagline: 'Savannah’s historic character is matched by a compassionate senior care community.' },
    'marietta':        { key: 'marietta',        name: 'Marietta',        state: 'GA', lat: 33.9526,  lon: -84.5499,  nearby: ['atlanta','savannah'],                        image: gsv(33.9526,  -84.5499,  270, 5, 80), tagline: 'Marietta is a growing hub for quality elder care in the Greater Atlanta area.' },

    // ── North Carolina ─────────────────────────────────────────────────────────
    'charlotte':       { key: 'charlotte',       name: 'Charlotte',       state: 'NC', lat: 35.2271,  lon: -80.8431,  nearby: ['raleigh','durham'],                          image: gsv(35.2271,  -80.8431,  180, 5, 80), tagline: 'Charlotte’s fast-growing senior population is well served by diverse elder care options.' },
    'raleigh':         { key: 'raleigh',         name: 'Raleigh',         state: 'NC', lat: 35.7796,  lon: -78.6382,  nearby: ['charlotte','durham'],                        image: gsv(35.7796,  -78.6382,  90,  5, 80), tagline: 'Raleigh’s research triangle brings innovation to the elder care field.' },
    'durham':          { key: 'durham',          name: 'Durham',          state: 'NC', lat: 35.9940,  lon: -78.8986,  nearby: ['raleigh','charlotte'],                       image: gsv(35.9940,  -78.8986,  270, 5, 80), tagline: 'Durham offers strong academic medical support for elder care families.' },

    // ── Arizona ────────────────────────────────────────────────────────────────
    'phoenix':         { key: 'phoenix',         name: 'Phoenix',         state: 'AZ', lat: 33.4484,  lon: -112.0740, nearby: ['scottsdale','mesa','tempe'],                 image: gsv(33.4484,  -112.0740, 90,  5, 80), tagline: 'Phoenix is one of the fastest-growing elder care markets in the United States.' },
    'scottsdale':      { key: 'scottsdale',      name: 'Scottsdale',      state: 'AZ', lat: 33.4942,  lon: -111.9261, nearby: ['phoenix','mesa'],                            image: gsv(33.4942,  -111.9261, 180, 5, 80), tagline: 'Scottsdale is known for premium senior living communities and specialized memory care.' },
    'mesa':            { key: 'mesa',            name: 'Mesa',            state: 'AZ', lat: 33.4152,  lon: -111.8315, nearby: ['phoenix','scottsdale','tempe'],               image: gsv(33.4152,  -111.8315, 270, 5, 80), tagline: 'Mesa offers a wide range of affordable and quality elder care options in the Valley.' },
    'tempe':           { key: 'tempe',           name: 'Tempe',           state: 'AZ', lat: 33.4255,  lon: -111.9400, nearby: ['phoenix','mesa','scottsdale'],                image: gsv(33.4255,  -111.9400, 90,  5, 80), tagline: 'Tempe’s growing senior population benefits from a strong network of local care providers.' },

    // ── Washington ─────────────────────────────────────────────────────────────
    'seattle':         { key: 'seattle',         name: 'Seattle',         state: 'WA', lat: 47.6062,  lon: -122.3321, nearby: ['bellevue','tacoma','kirkland'],               image: gsv(47.6062,  -122.3321, 180, 5, 80), tagline: 'Seattle’s elder care market is one of the most innovative in the Pacific Northwest.' },
    'bellevue':        { key: 'bellevue',        name: 'Bellevue',        state: 'WA', lat: 47.6101,  lon: -122.2015, nearby: ['seattle','kirkland'],                         image: gsv(47.6101,  -122.2015, 90,  5, 80), tagline: 'Bellevue offers premium senior living options with easy access to Seattle medical centers.' },
    'tacoma':          { key: 'tacoma',          name: 'Tacoma',          state: 'WA', lat: 47.2529,  lon: -122.4443, nearby: ['seattle','bellevue'],                         image: gsv(47.2529,  -122.4443, 270, 5, 80), tagline: 'Tacoma provides a growing range of elder care services for South Sound families.' },
    'kirkland':        { key: 'kirkland',        name: 'Kirkland',        state: 'WA', lat: 47.6769,  lon: -122.2060, nearby: ['bellevue','seattle'],                         image: gsv(47.6769,  -122.2060, 180, 5, 80), tagline: 'Kirkland’s lakeside community has excellent access to senior care throughout the Eastside.' },

    // ── Colorado ───────────────────────────────────────────────────────────────
    'denver':          { key: 'denver',          name: 'Denver',          state: 'CO', lat: 39.7392,  lon: -104.9903, nearby: ['aurora-co','boulder'],                        image: gsv(39.7392,  -104.9903, 90,  5, 80), tagline: 'Denver’s active senior community is supported by a robust elder care network.' },
    'aurora-co':       { key: 'aurora-co',       name: 'Aurora',          state: 'CO', lat: 39.7294,  lon: -104.8319, nearby: ['denver','boulder'],                           image: gsv(39.7294,  -104.8319, 180, 5, 80), tagline: 'Aurora offers diverse and affordable elder care options near Denver.' },
    'boulder':         { key: 'boulder',         name: 'Boulder',         state: 'CO', lat: 40.0150,  lon: -105.2705, nearby: ['denver','aurora-co'],                         image: gsv(40.0150,  -105.2705, 270, 5, 80), tagline: 'Boulder’s health-focused culture extends to exceptional elder care services.' },

    // ── Michigan ───────────────────────────────────────────────────────────────
    'detroit':         { key: 'detroit',         name: 'Detroit',         state: 'MI', lat: 42.3314,  lon: -83.0458,  nearby: ['ann-arbor','grand-rapids'],                   image: gsv(42.3314,  -83.0458,  90,  5, 80), tagline: 'Detroit’s elder care community is anchored by world-class health systems.' },
    'ann-arbor':       { key: 'ann-arbor',       name: 'Ann Arbor',       state: 'MI', lat: 42.2808,  lon: -83.7430,  nearby: ['detroit','grand-rapids'],                     image: gsv(42.2808,  -83.7430,  180, 5, 80), tagline: 'Ann Arbor’s University of Michigan health network supports exceptional elder care.' },
    'grand-rapids':    { key: 'grand-rapids',    name: 'Grand Rapids',    state: 'MI', lat: 42.9634,  lon: -85.6681,  nearby: ['detroit','ann-arbor'],                        image: gsv(42.9634,  -85.6681,  270, 5, 80), tagline: 'Grand Rapids has a strong faith-based elder care community with diverse options.' },

    // ── Virginia ───────────────────────────────────────────────────────────────
    'virginia-beach':  { key: 'virginia-beach',  name: 'Virginia Beach',  state: 'VA', lat: 36.8529,  lon: -75.9780,  nearby: ['norfolk','richmond'],                         image: gsv(36.8529,  -75.9780,  90,  5, 80), tagline: 'Virginia Beach’s coastal setting offers unique senior living and home care options.' },
    'richmond':        { key: 'richmond',        name: 'Richmond',        state: 'VA', lat: 37.5407,  lon: -77.4360,  nearby: ['virginia-beach','norfolk'],                   image: gsv(37.5407,  -77.4360,  180, 5, 80), tagline: 'Richmond’s historic character extends to compassionate elder care services.' },
    'norfolk':         { key: 'norfolk',         name: 'Norfolk',         state: 'VA', lat: 36.8508,  lon: -76.2859,  nearby: ['virginia-beach','richmond'],                  image: gsv(36.8508,  -76.2859,  270, 5, 80), tagline: 'Norfolk’s military and civilian communities benefit from strong elder care networks.' },

    // ── Tennessee ──────────────────────────────────────────────────────────────
    'nashville':       { key: 'nashville',       name: 'Nashville',       state: 'TN', lat: 36.1627,  lon: -86.7816,  nearby: ['memphis','knoxville'],                        image: gsv(36.1627,  -86.7816,  180, 5, 80), tagline: 'Nashville’s growing city is supported by a thriving elder care ecosystem.' },
    'memphis':         { key: 'memphis',         name: 'Memphis',         state: 'TN', lat: 35.1495,  lon: -90.0490,  nearby: ['nashville','knoxville'],                      image: gsv(35.1495,  -90.0490,  90,  5, 80), tagline: 'Memphis offers community-rooted elder care services with strong local providers.' },
    'knoxville':       { key: 'knoxville',       name: 'Knoxville',       state: 'TN', lat: 35.9606,  lon: -83.9207,  nearby: ['nashville','memphis'],                        image: gsv(35.9606,  -83.9207,  270, 5, 80), tagline: 'Knoxville’s elder care network reflects its strong community values.' },

    // ── Maryland ───────────────────────────────────────────────────────────────
    'baltimore':       { key: 'baltimore',       name: 'Baltimore',       state: 'MD', lat: 39.2904,  lon: -76.6122,  nearby: ['annapolis','silver-spring'],                  image: gsv(39.2904,  -76.6122,  90,  5, 80), tagline: 'Baltimore’s renowned medical institutions anchor a world-class elder care sector.' },
    'annapolis':       { key: 'annapolis',       name: 'Annapolis',       state: 'MD', lat: 38.9784,  lon: -76.4922,  nearby: ['baltimore','silver-spring'],                  image: gsv(38.9784,  -76.4922,  180, 5, 80), tagline: 'Annapolis offers a peaceful coastal setting with strong elder care provider options.' },

    // ── Massachusetts ──────────────────────────────────────────────────────────
    'boston':          { key: 'boston',          name: 'Boston',          state: 'MA', lat: 42.3601,  lon: -71.0589,  nearby: ['cambridge','worcester'],                      image: gsv(42.3601,  -71.0589,  90,  5, 80), tagline: 'Boston’s academic medical centers support an exceptional elder care community.' },
    'cambridge':       { key: 'cambridge',       name: 'Cambridge',       state: 'MA', lat: 42.3736,  lon: -71.1097,  nearby: ['boston','worcester'],                         image: gsv(42.3736,  -71.1097,  180, 5, 80), tagline: 'Cambridge’s innovative spirit shapes forward-thinking elder care services.' },
    'worcester':       { key: 'worcester',       name: 'Worcester',       state: 'MA', lat: 42.2626,  lon: -71.8023,  nearby: ['boston','cambridge'],                         image: gsv(42.2626,  -71.8023,  270, 5, 80), tagline: 'Worcester offers accessible elder care options for families across Central Massachusetts.' },
  };

  const CATEGORIES = {
    'home-care':       { key: 'home-care',       name: 'Home Care',          icon: '🏠', nonprofit: true, blurb: 'Daily non-medical support delivered in the comfort of home—bathing, meals, medication reminders, companionship.', cross: ['care-management', 'memory-care', 'elder-law'] },
    'assisted-living': { key: 'assisted-living', name: 'Assisted Living',    icon: '🏡', nonprofit: true, blurb: 'Community living with on-site staff, meals, activities, and medical support.',                                           cross: ['memory-care', 'care-management', 'home-care'] },
    'memory-care':     { key: 'memory-care',     name: 'Memory Care',        icon: '🧠', nonprofit: true, blurb: 'Specialized support for Alzheimer’s, dementia, and other memory conditions in a secure setting.',                    cross: ['assisted-living', 'home-care', 'hospice'] },
    'elder-law':       { key: 'elder-law',       name: 'Elder Law Attorney', icon: '⚖️', nonprofit: true, blurb: 'Guardianship, estate planning, Medicaid, and power of attorney from locally licensed attorneys.',                         cross: ['care-management', 'home-care', 'hospice'] },
    'care-management': { key: 'care-management', name: 'Care Management',    icon: '📋', nonprofit: true, blurb: 'A dedicated care manager coordinates every service—one family point of contact for the whole plan.',                  cross: ['home-care', 'elder-law', 'assisted-living'] },
    'hospice':         { key: 'hospice',         name: 'Hospice',            icon: '🤝', nonprofit: true, blurb: 'Compassionate end-of-life care at home or in a dedicated facility, with full family support.',                            cross: ['care-management', 'elder-law', 'memory-care'] },
    'grief-counselors':{ key: 'grief-counselors',name: 'Grief Counselors',   icon: '💜', nonprofit: true, blurb: 'Professional grief and bereavement support for families and seniors navigating loss, transition, and emotional healing.',   cross: ['hospice', 'care-management', 'home-care'] }
  };

  // Reusable package templates per category.
  const PACKAGE_TEMPLATES = {
    'home-care': [
      { tier: 'basic',    name: 'Essential Care',       unit: '/hr',      basePrice: 28,   features: ['Up to 4 hrs/day', 'Meal prep', 'Medication reminders', 'Light housekeeping'] },
      { tier: 'standard', name: 'Comprehensive Care',   unit: '/hr',      basePrice: 38,   popular: true, features: ['Up to 12 hrs/day', 'Personal care (bathing, dressing)', 'Medication reminders', 'Meal prep & housekeeping', 'Transportation'] },
      { tier: 'premium',  name: 'Premium 24/7',         unit: '/hr',      basePrice: 52,   features: ['Round-the-clock care', 'Dedicated caregiver team', 'Medical coordination', 'Weekly family reports', 'Priority scheduling'] }
    ],
    'assisted-living': [
      { tier: 'basic',    name: 'Studio Suite',         unit: '/mo',      basePrice: 3200, features: ['Private studio', 'Daily meals', 'Weekly housekeeping', 'Community activities'] },
      { tier: 'standard', name: 'One-Bedroom Care',     unit: '/mo',      basePrice: 4500, popular: true, features: ['1-bedroom apartment', 'All meals + snacks', 'Personal care assistance', 'Transportation', 'Wellness programs'] },
      { tier: 'premium',  name: 'Deluxe Suite',         unit: '/mo',      basePrice: 6100, features: ['Two-bedroom suite', 'Chef-prepared meals', '24/7 medical staff', 'Private transportation', 'Concierge service'] }
    ],
    'memory-care': [
      { tier: 'basic',    name: 'Day Program',          unit: '/mo',      basePrice: 2400, features: ['5 days/week, 8 hrs/day', 'Cognitive activities', 'Lunch & snacks', 'Caregiver respite'] },
      { tier: 'standard', name: 'Residential Care',     unit: '/mo',      basePrice: 5200, popular: true, features: ['24/7 secure residence', 'Specialized dementia staff', 'Personal care', 'Family support program'] },
      { tier: 'premium',  name: 'Premier Memory Suite', unit: '/mo',      basePrice: 7400, features: ['Private suite', '1:3 caregiver ratio', 'Cognitive therapy program', 'Weekly family updates', 'On-site geriatric MD'] }
    ],
    'elder-law': [
      { tier: 'basic',    name: 'Consultation',         unit: 'flat',     basePrice: 250,  features: ['1-hour attorney consult', 'Document review', 'Written summary'] },
      { tier: 'standard', name: 'Estate Planning',      unit: 'flat',     basePrice: 1800, popular: true, features: ['Will + trust preparation', 'Power of attorney', 'Healthcare directives', 'Two revision rounds'] },
      { tier: 'premium',  name: 'Full Guardianship',    unit: 'flat',     basePrice: 4500, features: ['Court petition & representation', 'Guardianship setup', 'Estate plan', 'Annual compliance review'] }
    ],
    'care-management': [
      { tier: 'basic',    name: 'Care Assessment',      unit: 'flat',     basePrice: 400,  features: ['In-home assessment', 'Written care plan', '30-day follow-up'] },
      { tier: 'standard', name: 'Ongoing Management',   unit: '/mo',      basePrice: 950,  popular: true, features: ['Monthly visits', 'Provider coordination', 'Family progress reports', 'Emergency support line'] },
      { tier: 'premium',  name: 'Executive Care',       unit: '/mo',      basePrice: 1800, features: ['Weekly visits', 'Full provider coordination', '24/7 family hotline', 'Quarterly medical review', 'Tax & financial liaison'] }
    ],
    'hospice': [
      { tier: 'basic',    name: 'Routine Home Care',    unit: 'covered',  basePrice: 0,    features: ['Nurse visits 2x/week', 'Aide support', 'Bereavement counseling', 'Medicare covered'] },
      { tier: 'standard', name: 'Continuous Care',      unit: 'covered',  basePrice: 0,    popular: true, features: ['Daily nurse visits', 'Aide support 5 days/week', 'Social worker', 'Chaplain', 'Medicare covered'] },
      { tier: 'premium',  name: 'Inpatient Facility',   unit: '/day',     basePrice: 320,  features: ['Private room in facility', '24/7 nursing', 'Full family accommodations', 'Music & pet therapy', 'Concierge support'] }
    ],
    'grief-counselors': [
      { tier: 'basic',    name: 'Individual Session',   unit: '/session', basePrice: 120,  features: ['50-min one-on-one session', 'Grief assessment', 'Coping strategies', 'Follow-up resources'] },
      { tier: 'standard', name: 'Ongoing Support',      unit: '/mo',      basePrice: 380,  popular: true, features: ['4 sessions/month', 'Group session access', 'Family consultation', 'Crisis line support'] },
      { tier: 'premium',  name: 'Comprehensive Care',   unit: '/mo',      basePrice: 650,  features: ['Unlimited sessions', 'GriefShare program access', 'Family & individual care', 'Bereavement coordination', 'Chaplain connection'] }
    ]
  };

  // Build packages for a provider by taking templates and adjusting with a tier multiplier.
  function packagesFor(category, providerBoost) {
    const tmpl = PACKAGE_TEMPLATES[category] || [];
    return tmpl.map(pkg => ({
      tier: pkg.tier,
      name: pkg.name,
      unit: pkg.unit,
      popular: !!pkg.popular,
      price: pkg.basePrice ? Math.round(pkg.basePrice * (providerBoost || 1)) : 0,
      features: pkg.features.slice()
    }));
  }

  // Providers — compact definitions, packages generated from template.
  const PROVIDER_SEEDS = [
    // Home Care
    { id: 'sunrise-home-care',     name: 'Sunrise Home Care',           category: 'home-care',       city: 'dallas',     tier: 'featured', rating: 4.9, reviews: 128, boost: 1.00, color: '#C4933A', tagline: 'Compassionate in-home caregivers since 2008', specialties: ['Dementia care', 'Post-surgery', 'Companion care'], about: 'Sunrise has served DFW families for 17 years with fully vetted, bonded caregivers. Our care plans are built around your loved one’s routine—not the other way around.' },
    { id: 'carefirst-dallas',      name: 'CareFirst Dallas',            category: 'home-care',       city: 'dallas',     tier: 'premium',  rating: 4.7, reviews: 92,  boost: 0.95, color: '#7A9E7E', tagline: 'Family-owned, locally run home care', nonprofit: true, specialties: ['Mobility support', 'Chronic illness'],                           about: 'A family-owned agency matching caregivers to clients by personality and care needs. Clear hourly rates, no long contracts.' },
    { id: 'lone-star-aides',       name: 'Lone Star Home Aides',        category: 'home-care',       city: 'fort-worth', tier: 'premium',  rating: 4.8, reviews: 74,  boost: 0.98, color: '#8B5CF6', tagline: 'Fort Worth’s trusted caregiver network',           specialties: ['Overnight care', 'Diabetes management'],                         about: 'Covering all of Tarrant County with 40+ certified caregivers on staff. Same-day starts available.' },
    { id: 'comfortcare-plano',     name: 'ComfortCare Plano',           category: 'home-care',       city: 'plano',      tier: 'basic',    rating: 4.5, reviews: 41,  boost: 0.85, color: '#3B82F6', tagline: 'Hourly and live-in care in North Dallas',               specialties: ['Companion care', 'Meal preparation'],                            about: 'Plano’s neighborhood home care agency. Simple pricing, flexible hours.' },

    // Assisted Living
    { id: 'lakewood-assisted',     name: 'Lakewood Assisted Living',    category: 'assisted-living', city: 'dallas',     tier: 'featured', rating: 4.8, reviews: 156, boost: 1.00, color: '#0EA5E9', tagline: 'Boutique community in East Dallas',                     specialties: ['Chef dining', 'Art programs'],                                   about: 'A 48-unit boutique community with private gardens and chef-led dining. Staff-to-resident ratio 1:4.' },
    { id: 'heritage-village-fw',   name: 'Heritage Village',            category: 'assisted-living', city: 'fort-worth', tier: 'premium',  rating: 4.7, reviews: 98,  boost: 0.95, color: '#F59E0B', tagline: 'Texas-style hospitality meets senior living',           specialties: ['Outdoor activities', 'Pet-friendly'],                            about: 'A full-service community with Texas-style warmth. On-site physical therapy, chapel, and dog park.' },
    { id: 'plano-senior-living',   name: 'Plano Senior Living',         category: 'assisted-living', city: 'plano',      tier: 'basic',    rating: 4.4, reviews: 52,  boost: 0.88, color: '#6366F1', tagline: 'Affordable assisted living in Collin County',            specialties: ['Budget-friendly', 'Social events'],                              about: 'A 60-unit community focused on affordability without sacrificing safety or care quality.' },

    // Memory Care
    { id: 'memory-haven-dallas',   name: 'Memory Haven Dallas',         category: 'memory-care',     city: 'dallas',     tier: 'featured', rating: 4.9, reviews: 87,  boost: 1.00, color: '#EC4899', tagline: 'Secure memory care with 1:3 staff ratio',               specialties: ['Dementia', 'Alzheimer’s'],                                   about: 'Purpose-built memory care residence with sensory gardens and a specialty dementia program.' },
    { id: 'clear-days-arlington',  name: 'Clear Days Memory Care',      category: 'memory-care',     city: 'arlington',  tier: 'premium',  rating: 4.6, reviews: 63,  boost: 0.95, color: '#A855F7', tagline: 'A calmer day. A brighter tomorrow.',                    specialties: ['Early-stage dementia', 'Music therapy'],                         about: 'Programs designed around residents’ remaining abilities, not their diagnoses. Family support groups included.' },

    // Elder Law
    { id: 'bradford-elder-law',    name: 'Bradford Elder Law',          category: 'elder-law',       city: 'dallas',     tier: 'featured', rating: 5.0, reviews: 54,  boost: 1.00, color: '#1E40AF', tagline: '30 years of Texas elder law practice',                 specialties: ['Medicaid planning', 'Guardianship', 'Estate'],                   about: 'Founded in 1995, Bradford has handled 1,200+ guardianship and Medicaid cases across Texas courts.' },
    { id: 'fw-elder-law-group',    name: 'Fort Worth Elder Law Group',  category: 'elder-law',       city: 'fort-worth', tier: 'premium',  rating: 4.8, reviews: 38,  boost: 0.95, color: '#0F766E', tagline: 'Estate planning & guardianship attorneys',              specialties: ['Estate planning', 'Probate'],                                    about: 'Small team of four attorneys focused exclusively on elder law. Personal attention on every case.' },

    // Care Management
    { id: 'guided-care-mgmt',      name: 'Guided Care Management',      category: 'care-management', city: 'dallas',     tier: 'featured', rating: 4.9, reviews: 66,  boost: 1.00, color: '#7A9E7E', tagline: 'One point of contact for everything',                   specialties: ['Complex care coordination'],                                     about: 'Board-certified geriatric care managers who coordinate every doctor, therapist, and caregiver so families can breathe.' },
    { id: 'plano-care-advisors',   name: 'Plano Care Advisors',         category: 'care-management', city: 'plano',      tier: 'premium',  rating: 4.7, reviews: 42,  boost: 0.95, color: '#D97706', tagline: 'Senior care navigation in North Dallas',                specialties: ['Transition planning', 'Family mediation'],                       about: 'We guide families through the toughest transitions—from hospital discharge to long-term care placement.' },

    // Hospice
    { id: 'compassion-hospice',    name: 'Compassion Hospice',          category: 'hospice',         city: 'dallas',     tier: 'featured', rating: 4.9, reviews: 109, boost: 1.00, color: '#7A9E7E', tagline: 'Medicare-certified hospice with music & pet therapy',   specialties: ['Pain management', 'Bereavement'],                                about: 'Full interdisciplinary team including chaplains, social workers, and volunteer musicians. 24/7 on-call nursing.' },
    { id: 'serenity-hospice',      name: 'Serenity Hospice TX',         category: 'hospice',         city: 'arlington',  tier: 'premium',  rating: 4.8, reviews: 81,  boost: 0.95, color: '#0891B2', tagline: 'Patient-first hospice serving Tarrant County',          specialties: ['Comfort care', 'Family counseling'],                             about: 'A non-profit hospice agency—every dollar goes back into patient and family support.' },
    { id: 'peaceful-transitions',  name: 'Peaceful Transitions',        category: 'hospice',         city: 'fort-worth', tier: 'basic',    rating: 4.5, reviews: 35,  boost: 0.90, color: '#9333EA', tagline: 'Compassionate end-of-life care at home',                specialties: ['In-home hospice'],                                               about: 'Small hospice team serving Fort Worth families in the comfort of their own homes.' },

    // Grief Counselors
    { id: 'healing-path-grief',    name: 'Healing Path Grief Center',    category: 'grief-counselors', city: 'dallas',     tier: 'featured', rating: 4.9, reviews: 78,  boost: 1.00, color: '#9333EA', tagline: 'Compassionate grief support for families and seniors',     specialties: ['Grief Counseling', 'Bereavement Counseling', 'Grief Support Services'], about: 'Healing Path has guided over 600 Dallas families through loss. Our licensed counselors specialise in elder bereavement, anticipatory grief, and family grief work.' },
    { id: 'griefshare-dallas',     name: 'GriefShare Dallas',            category: 'grief-counselors', city: 'dallas',     tier: 'premium',  rating: 4.8, reviews: 112, boost: 0.95, color: '#7A9E7E', tagline: 'Faith-based grief recovery program', nonprofit: true,       specialties: ['GriefShare', 'Grief Support Group', 'Bereavement Care'],          about: 'A 13-week faith-based recovery program with weekly group sessions, personal workbooks, and one-on-one pastoral care.' },
    { id: 'comfort-grief-fw',      name: 'Comfort & Courage Counseling', category: 'grief-counselors', city: 'fort-worth', tier: 'premium',  rating: 4.7, reviews: 55,  boost: 0.96, color: '#EC4899', tagline: 'Individual and family grief therapy in Fort Worth',          specialties: ['Grief Counseling', 'Bereavement Counseling', 'Grief Support Community'], about: 'Licensed therapists offering individual and family sessions, grief support circles, and coordination with local hospice teams.' },
    { id: 'community-grief-plano', name: 'Community Grief Support',      category: 'grief-counselors', city: 'plano',      tier: 'basic',    rating: 4.6, reviews: 43,  boost: 0.88, color: '#0EA5E9', tagline: 'Grief support groups and services in North Dallas', nonprofit: true, specialties: ['Grief Support Group', 'Grief Support Services', 'Grief Support Community'], about: 'A non-profit offering free and low-cost grief support groups, monthly community events, and bereavement resource navigation.' },

    // Free (unclaimed) listings
    { id: 'allcare-home-dallas',  name: 'AllCare Home Services',        category: 'home-care',       city: 'dallas',     tier: 'free', rating: 3.9, reviews: 5,  boost: 0.80, color: '#A0A0A0', tagline: 'Home care services in the Dallas area.',    specialties: ['Companion care'],   about: 'Unclaimed listing.', claimed: false },
    { id: 'sunridge-assisted',    name: 'Sunridge Assisted Care',       category: 'assisted-living', city: 'dallas',     tier: 'free', rating: 4.0, reviews: 8,  boost: 0.80, color: '#B0B0B0', tagline: 'Assisted living community in Dallas.',       specialties: ['Daily assistance'], about: 'Unclaimed listing.', claimed: false },
    { id: 'maplewood-memory',     name: 'Maplewood Memory Unit',        category: 'memory-care',     city: 'dallas',     tier: 'free', rating: 3.8, reviews: 3,  boost: 0.80, color: '#C0C0C0', tagline: 'Memory care services in Dallas.',            specialties: ['Dementia'],         about: 'Unclaimed listing.', claimed: false }
  ];

  const REVIEW_POOL = [
    { author: 'Jennifer L.', rating: 5, date: 'Mar 2026', text: 'Absolutely wonderful team. Within a day we had a caregiver lined up for my father post-surgery—they really listened.' },
    { author: 'Robert M.',   rating: 5, date: 'Feb 2026', text: 'They matched us with a caregiver who felt like family within a week. Highly recommend.' },
    { author: 'Anita G.',    rating: 4, date: 'Feb 2026', text: 'Professional team, clear pricing. We needed more help on short notice and they made it work.' },
    { author: 'Diego R.',    rating: 5, date: 'Jan 2026', text: 'The coordinator checked in every week. Made a stressful time so much easier for our family.' },
    { author: 'Sandra K.',   rating: 5, date: 'Jan 2026', text: 'Everyone we worked with was kind, patient, and deeply skilled. Mom felt safe, which is what mattered most.' }
  ];

  const PROVIDERS = PROVIDER_SEEDS.map((seed, idx) => ({
    ...seed,
    initials: seed.name.split(' ').slice(0, 2).map(w => w[0]).join(''),
    verified: true,
    response: seed.tier === 'featured' ? 'Responds within 1 hour' : (seed.tier === 'premium' ? 'Responds within 4 hours' : 'Responds within 24 hours'),
    packages: seed.tier === 'free' ? [] : packagesFor(seed.category, seed.boost),
    reviews_list: [REVIEW_POOL[idx % REVIEW_POOL.length], REVIEW_POOL[(idx + 2) % REVIEW_POOL.length]]
  }));

  // ── Public API ───────────────────────────────────────────────────────────────
  const API = {
    cities:     CITIES,
    categories: CATEGORIES,
    providers:  PROVIDERS,

    normalizeCityKey(str) {
      return String(str || '').toLowerCase().trim().replace(/\s+/g, '-').replace(/[^a-z0-9-]/g, '');
    },

    getCity(keyOrName) {
      if (!keyOrName) return null;
      const key = this.normalizeCityKey(keyOrName);
      return this.cities[key] || null;
    },

    getCategory(key) { return this.categories[key] || null; },

    getProvider(id) { return this.providers.find(p => p.id === id) || null; },

    getProvidersByCategoryCity(categoryKey, cityKey) {
      const tierOrder = { featured: 0, premium: 1, basic: 2 };
      return this.providers
        .filter(p => p.category === categoryKey && (!cityKey || p.city === cityKey))
        .sort((a, b) => (tierOrder[a.tier] - tierOrder[b.tier]) || (b.rating - a.rating));
    },

    getProvidersInNearbyCities(categoryKey, cityKey, limit) {
      const nearby = ((this.cities[cityKey] || {}).nearby) || [];
      const results = [];
      for (const n of nearby) {
        const matches = this.providers.filter(p => p.category === categoryKey && p.city === n);
        results.push(...matches);
      }
      return results.slice(0, limit || 6);
    },

    getCrossSell(categoryKey) {
      const cat = this.categories[categoryKey];
      if (!cat) return [];
      return (cat.cross || []).map(k => this.categories[k]).filter(Boolean);
    },

    search(query, opts) {
      opts = opts || {};
      const q = String(query || '').toLowerCase().trim();
      return this.providers.filter(p => {
        if (opts.category && p.category !== opts.category) return false;
        if (opts.city    && p.city     !== opts.city)     return false;
        if (!q) return true;
        const hay = [
          p.name, p.tagline, (p.specialties || []).join(' '),
          (this.categories[p.category] || {}).name || '',
          (this.cities[p.city]         || {}).name || ''
        ].join(' ').toLowerCase();
        return hay.includes(q);
      });
    }
  };

  window.ECM_DATA = API;
})();
