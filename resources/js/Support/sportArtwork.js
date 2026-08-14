/**
 * Curated fallback artwork for the seeded SIKLAB sports and judged events.
 *
 * These are Wikimedia Commons files (the `source` link is the attribution
 * page). Uploaded competition covers are resolved by the consuming page
 * before this helper is called, so these remain safe, non-authoritative
 * fallbacks for entries without a cover.
 */
const commons = (file, source = file) => ({
    url: `https://commons.wikimedia.org/wiki/Special:Redirect/file/${file}?width=1200`,
    source: `https://commons.wikimedia.org/wiki/File:${source}`,
});

const artwork = {
    basketball: {
        ...commons('Donovan_mitchell_directing_traffic_vs_Lakers_2022.jpg'),
        alt: 'NBA guard Donovan Mitchell directing play during a 2022 game.',
        position: 'center 52%',
        accent: '#D18B2C',
    },
    volleyball: {
        ...commons('Volleyball_player_jumping.jpg'),
        alt: 'A volleyball player jumping to play the ball.',
        position: 'center 36%',
        accent: '#287A9B',
    },
    badminton: {
        ...commons('%C3%89preuve_Badminton_Jeux_Olympiques_2024_Arena_Porte_Chapelle_-_Paris_XVIII_%28FR75%29_-_2024-07-28_-_30.jpg'),
        alt: 'Players contesting a mixed-doubles badminton rally at the Paris 2024 Olympics.',
        position: 'center 42%',
        accent: '#27866A',
    },
    tableTennis: {
        ...commons('Competitive_table_tennis.jpg'),
        alt: 'Players competing in a table tennis match.',
        position: 'center 46%',
        accent: '#B14B32',
    },
    lawnTennis: {
        ...commons('Wimbledon_2023_Semifinals_%2853044958340%29_%28cropped%29.jpg'),
        alt: 'A player serving during a 2023 Wimbledon semifinal.',
        position: 'center 48%',
        accent: '#4A8151',
    },
    sepakTakraw: {
        ...commons('Sepak_Takraw_(3828519859)_(cropped).jpg', 'Sepak_Takraw_%283828519859%29_%28cropped%29.jpg'),
        alt: 'A player performing a sepak takraw kick in the Philippines.',
        position: 'center 45%',
        accent: '#A8752C',
    },
    chess: {
        ...commons('TataSteelChess2019-3.jpg'),
        alt: 'A chess player competing at a 2019 tournament.',
        position: 'center 58%',
        accent: '#8A6D45',
    },
    taekwondo: {
        ...commons('Taekwondo_Fight_01.jpg'),
        alt: 'Two athletes sparring in a taekwondo match.',
        position: 'center 42%',
        accent: '#9B3A35',
    },
    arnis: {
        ...commons('Eskrima_Kombat.jpg'),
        alt: 'Arnis practitioners competing in an eskrima tournament.',
        position: 'center 52%',
        accent: '#895F38',
    },
    athletics: {
        ...commons('Athletics_track.jpg'),
        alt: 'Starting blocks on an athletics running track.',
        position: 'center 66%',
        accent: '#B64B36',
    },
    sprint: {
        ...commons('Kim_Kuk-young_and_Brijesh_Lawrence_Rio_2016.jpg'),
        alt: 'Sprinters competing in a 100-metre race.',
        position: 'center 55%',
        accent: '#C65B3B',
    },
    middleDistance: {
        ...commons('CNU_Christopher_Newport_University_Virginia_Va._Captains_Classic_Track_and_Field_meet_women%27s_1500_meter_1500m_run_running_NCAA_%2816575147984%29.jpg', 'CNU_Christopher_Newport_University_Virginia_Va._Captains_Classic_Track_and_Field_meet_women%27s_1500_meter_1500m_run_running_NCAA_%2816575147984%29.jpg'),
        alt: 'Runners competing in a 1500-metre track race.',
        position: 'center 50%',
        accent: '#C65B3B',
    },
    shotPut: {
        ...commons('Darlan_Romani_Rio_2016.jpg'),
        alt: 'An athlete competing in the shot put event.',
        position: 'center 44%',
        accent: '#7B5B43',
    },
    discus: {
        ...commons('MelinaRobert-MichonRio2016.jpg'),
        alt: 'An athlete throwing the discus in an athletics competition.',
        position: 'center 45%',
        accent: '#7B5B43',
    },
    longJump: {
        ...commons('Long_jumper_Mohammad_Arzandeh_at_the_2016_Olympics_08.jpg'),
        alt: 'An athlete competing in the long jump.',
        position: 'center 46%',
        accent: '#8A6C3D',
    },
    tripleJump: {
        ...commons('Triple_jump.jpg'),
        alt: 'An athlete competing in the triple jump.',
        position: 'center 50%',
        accent: '#8A6C3D',
    },
    publicSpeaking: {
        ...commons('Speaking_To_An_Empty_Audience_-_Public_Speaking.jpg'),
        alt: 'A speaker rehearsing a speech before an audience.',
        position: 'center 46%',
        accent: '#2A6E83',
    },
    storytelling: {
        ...commons('Cherokee_storyteller_Matthew_Tooni_talks_to_students_as_Ranger_Misty_Benton_looks_on%2C_11-7-2018--Chalice_Keith_%2844872487395%29.jpg'),
        alt: 'Cherokee storyteller Matthew Tooni sharing a story with children.',
        position: 'center 42%',
        accent: '#A46B31',
    },
    pagkukwento: {
        ...commons('Renowned_authors_Modern_gods_Philippine_spirits%2C_myths_legends_book_festival.jpg'),
        alt: 'Filipino authors discussing Philippine myths and legends at a 2026 book-festival storytelling panel.',
        position: 'center 46%',
        accent: '#8A5B77',
    },
    radioDrama: {
        ...commons('Recording_a_radio_drama.jpg'),
        alt: 'Three performers reading a radio drama into studio microphones.',
        position: 'center 44%',
        accent: '#6B5268',
    },
    popSolo: {
        ...commons('A_musician_passionately_sings_into_a_microphone_on_stage.jpg'),
        alt: 'A solo vocalist singing into a studio microphone.',
        position: 'center 42%',
        accent: '#A24E69',
    },
    kundiman: {
        ...commons('Gerphil_Singing_Classic_Filipino_traditional_Songs.jpg'),
        alt: 'A Filipino vocalist performing traditional songs including kundiman.',
        position: 'center 38%',
        accent: '#9D4D4D',
    },
    vocalDuet: {
        ...commons('Madrid_-_San_Isidro_2026_-_Amistades_Peligrosas_-_260514_204305.jpg'),
        alt: 'Spanish pop duo Amistades Peligrosas singing together at a 2026 Madrid concert.',
        position: 'center 46%',
        accent: '#72526A',
    },
    bandurria: {
        ...commons('Padayon_Rondalla_performer13.jpg'),
        alt: 'A rondalla performer playing a traditional Filipino string instrument.',
        position: 'center 52%',
        accent: '#A36A32',
    },
    piano: {
        ...commons('A_performance_of_a_piano_concerto.jpg'),
        alt: 'A pianist performing with an orchestra.',
        position: 'center 45%',
        accent: '#594E59',
    },
    classicalGuitar: {
        ...commons('Johannes_Moller_playing_classical_guitar.jpg'),
        alt: 'A guitarist performing classical guitar on stage.',
        position: 'center 50%',
        accent: '#9A633E',
    },
    folkDance: {
        ...commons('The_National_Dance_of_The_Philippines_with_Bamboo_Poles.jpg'),
        alt: 'Performers dancing the Filipino folk dance Tinikling.',
        position: 'center 48%',
        accent: '#9A5D43',
    },
    hipHopDance: {
        ...commons('Next_Level_Hip-hop_Performance_DC_%2835711084422%29.jpg', 'Next_Level_Hip-hop_Performance_DC_%2835711084422%29.jpg'),
        alt: 'A dancer performing hip-hop choreography.',
        position: 'center 50%',
        accent: '#4C5D8E',
    },
    contemporaryDance: {
        ...commons('Contemporary_Dance.jpg'),
        alt: 'Dancers performing contemporary dance on stage.',
        position: 'center 46%',
        accent: '#7C587A',
    },
    danceSports: {
        ...commons('Ballroom_dances_2018_03.jpg'),
        alt: 'A pair competing in a ballroom dance competition.',
        position: 'center 43%',
        accent: '#9A5870',
    },
    cheerDance: {
        ...commons('Dallas_Cowboys_Cheerleaders_Performance_-_U.S._Army_Garrison_Humphreys%2C_South_Korea_-_21_December_2011_%286558039159%29.jpg', 'Dallas_Cowboys_Cheerleaders_Performance_-_U.S._Army_Garrison_Humphreys%2C_South_Korea_-_21_December_2011_%286558039159%29.jpg'),
        alt: 'A cheerleading team performing a choreographed routine.',
        position: 'center 46%',
        accent: '#B34A54',
    },
    charcoal: {
        ...commons('Dan_Pyle_-_West_Hollywood_Porsche_2018.jpg'),
        alt: 'A contemporary charcoal rendering by artist Dan Pyle.',
        position: 'center 50%',
        accent: '#665C57',
    },
    pencil: {
        ...commons('Pencil_Sketch_artist.jpg'),
        alt: 'An artist making a pencil sketch.',
        position: 'center 48%',
        accent: '#6D7070',
    },
    painting: {
        ...commons('A_painter_at_work.jpg'),
        alt: 'A contemporary painter working on a canvas.',
        position: 'center 50%',
        accent: '#9D704A',
    },
    posterMaking: {
        ...commons('Pride_Walk_Kolkata_2019_Poster_Workshop_MA32.jpg'),
        alt: 'A participant painting a colorful poster during the 2019 Kolkata Rainbow Pride Walk poster workshop.',
        position: 'center 48%',
        accent: '#A35E4A',
    },
    photography: {
        ...commons('Photographer-camera.jpg'),
        alt: 'A photographer composing a picture with a camera.',
        position: 'center 48%',
        accent: '#496E7D',
    },
    essayWriting: {
        ...commons('Woman_writing_on_a_notebook_with_a_pen.jpg'),
        alt: 'A writer composing an essay in a notebook.',
        position: 'center 50%',
        accent: '#6C695C',
    },
};

const eventArtwork = new Map([
    ['basketball', artwork.basketball],
    ['volleyball', artwork.volleyball],
    ['badminton', artwork.badminton],
    ['table tennis', artwork.tableTennis],
    ['lawn tennis', artwork.lawnTennis],
    ['sepak takraw', artwork.sepakTakraw],
    ['chess', artwork.chess],
    ['taekwondo', artwork.taekwondo],
    ['arnis', artwork.arnis],
    ['athletics', artwork.athletics],
    ['extemporaneous speaking', artwork.publicSpeaking],
    ['dagliang talumpati', artwork.publicSpeaking],
    ['story telling', artwork.storytelling],
    ['pagkukwento', artwork.pagkukwento],
    ['pagkukuwento', artwork.pagkukwento],
    ['radio drama', artwork.radioDrama],
    ['pop solo', artwork.popSolo],
    ['kundiman', artwork.kundiman],
    ['vocal duet', artwork.vocalDuet],
    ['instrumental solo - bandurria', artwork.bandurria],
    ['instrumental solo - piano', artwork.piano],
    ['instrumental solo - classical guitar', artwork.classicalGuitar],
    ['folk dance', artwork.folkDance],
    ['hip hop dance', artwork.hipHopDance],
    ['contemporary dance', artwork.contemporaryDance],
    ['charcoal rendering', artwork.charcoal],
    ['pencil drawing', artwork.pencil],
    ['painting', artwork.painting],
    ['on-the-spot poster making', artwork.posterMaking],
    ['photography', artwork.photography],
    ['essay writing', artwork.essayWriting],
    ['pagsulat ng sanaysay', artwork.essayWriting],
    ['dance sports', artwork.danceSports],
    ['cheer dance', artwork.cheerDance],
    ['100m', artwork.sprint],
    ['200m', artwork.sprint],
    ['400m', artwork.sprint],
    ['800m', artwork.middleDistance],
    ['1500m', artwork.middleDistance],
    ['3000m', artwork.middleDistance],
    ['4 x 100m relay', artwork.sprint],
    ['4 x 400m relay', artwork.sprint],
    ['shot put', artwork.shotPut],
    ['discus', artwork.discus],
    ['long jump', artwork.longJump],
    ['triple jump', artwork.tripleJump],
]);

function normalizeName(name) {
    return String(name)
        .normalize('NFKD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .replace(/[–—−]/g, '-')
        .replace(/[×]/g, 'x')
        .replace(/\s+/g, ' ')
        .trim();
}

export function getSportArtwork(name = '') {
    const value = normalizeName(name);
    const exact = eventArtwork.get(value);
    if (exact) return exact;

    // Preserve the useful narrow aliases from the original helper for custom
    // divisions, while keeping broad/unknown sports unassigned.
    if (value.includes('basketball')) return artwork.basketball;
    if (value.includes('volleyball')) return artwork.volleyball;
    if (value.includes('badminton')) return artwork.badminton;
    if (value.includes('table tennis')) return artwork.tableTennis;
    if (value.includes('lawn tennis')) return artwork.lawnTennis;
    if (value.includes('sepak takraw')) return artwork.sepakTakraw;
    if (value.includes('chess')) return artwork.chess;
    if (value.includes('taekwondo')) return artwork.taekwondo;
    if (value.includes('arnis')) return artwork.arnis;
    if (value.includes('athletic') || value.includes('track')) return artwork.athletics;
    if (value.includes('relay')) return artwork.sprint;

    return null;
}
