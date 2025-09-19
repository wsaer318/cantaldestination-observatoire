const axios = require('axios');
const cheerio = require('cheerio');

function parserDateFrancaise(texteDate) {
    /**
     * parser une date francaise au format iso aaaa-mm-jj
     * 
     * @param {string} texteDate - texte contenant une date francaise
     * @returns {string} date au format aaaa-mm-jj ou texte original si parsing echoue
     */
    try {
        // mapping des mois francais
        const moisFrancais = {
            'janvier': '01', 'fevrier': '02', 'mars': '03', 'avril': '04',
            'mai': '05', 'juin': '06', 'juillet': '07', 'aout': '08',
            'septembre': '09', 'octobre': '10', 'novembre': '11', 'decembre': '12',
            'décembre': '12'  // version avec accent
        };
        
        // pattern pour capturer jour, mois et annee
        // exemple: "jeudi 20 mars 2025 a 10:01"
        // support des caracteres accentues avec [\w\u00C0-\u017F]
        const pattern = /(\d{1,2})\s+([\w\u00C0-\u017F]+)\s+(20\d{2})/;
        const match = texteDate.toLowerCase().match(pattern);
        
        if (match) {
            const jour = match[1].padStart(2, '0');  // ajouter 0 si necessaire
            const moisNom = match[2];
            const annee = match[3];
            
            // convertir le nom du mois en numero
            if (moisFrancais[moisNom]) {
                const moisNum = moisFrancais[moisNom];
                return `${annee}-${moisNum}-${jour}`;
            }
        }
        
        // si parsing echoue, retourner le texte original
        return texteDate;
        
    } catch (error) {
        return texteDate;
    }
}

function calculerPeriodesSeasons(resultats) {
    /**
     * calculer les periodes de debut et fin pour chaque saison
     * 
     * @param {Object} resultats - donnees des saisons par annee
     * @returns {Object} donnees avec debut et fin pour chaque saison
     */
    const resultatsAvecPeriodes = {};
    
    // ordre chronologique des saisons dans l'annee
    const ordreSaisons = ['printemps', 'ete', 'automne', 'hiver'];
    
    for (const [annee, saisons] of Object.entries(resultats)) {
        resultatsAvecPeriodes[annee] = {};
        
        // trier les saisons par ordre chronologique
        const saisonsTriees = {};
        ordreSaisons.forEach(saison => {
            if (saisons[saison]) {
                saisonsTriees[saison] = saisons[saison];
            }
        });
        
        const saisonsCles = Object.keys(saisonsTriees);
        const saisonsValeurs = Object.values(saisonsTriees);
        
        for (let i = 0; i < saisonsCles.length; i++) {
            const saison = saisonsCles[i];
            const dateDebut = saisonsValeurs[i];
            
            // calculer la date de fin (debut de la saison suivante - 1 jour)
            let dateFin;
            if (i < saisonsCles.length - 1) {
                // saison suivante dans la meme annee
                const dateDebutSuivante = new Date(saisonsValeurs[i + 1]);
                dateDebutSuivante.setDate(dateDebutSuivante.getDate() - 1);
                dateFin = dateDebutSuivante.toISOString().split('T')[0];
            } else {
                // derniere saison de l'annee (hiver) -> fin = debut du printemps suivant - 1 jour
                const anneeSuivante = parseInt(annee) + 1;
                if (resultats[anneeSuivante] && resultats[anneeSuivante]['printemps']) {
                    const dateDebutPrintemps = new Date(resultats[anneeSuivante]['printemps']);
                    dateDebutPrintemps.setDate(dateDebutPrintemps.getDate() - 1);
                    dateFin = dateDebutPrintemps.toISOString().split('T')[0];
                } else {
                    // si pas d'annee suivante, fin = 31 decembre
                    dateFin = `${annee}-12-31`;
                }
            }
            
            resultatsAvecPeriodes[annee][saison] = {
                debut: dateDebut,
                fin: dateFin
            };
        }
    }
    
    return resultatsAvecPeriodes;
}

function calculerDureeJours(dateDebut, dateFin) {
    /**
     * calculer la duree en jours entre deux dates
     */
    const debut = new Date(dateDebut);
    const fin = new Date(dateFin);
    const diffTime = fin - debut;
    return Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1; // +1 pour inclure le jour de fin
}

function genererDonneesPHP(resultatsAvecPeriodes) {
    /**
     * generer un fichier PHP avec les donnees pour import automatique
     */
    const fs = require('fs');
    const maintenant = new Date().toISOString();
    
    let phpContent = `<?php
// Donnees des saisons astronomiques generees automatiquement
// Genere le ${maintenant}
// Source: icalendrier.fr (Institut de mecanique celeste et de calcul des ephemerides)

return [
`;
    
    for (const [annee, saisons] of Object.entries(resultatsAvecPeriodes)) {
        for (const [saison, periode] of Object.entries(saisons)) {
            const duree = calculerDureeJours(periode.debut, periode.fin);
            phpContent += `    ['annee' => ${annee}, 'saison' => '${saison}', 'date_debut' => '${periode.debut}', 'date_fin' => '${periode.fin}', 'duree_jours' => ${duree}],\n`;
        }
    }
    
    phpContent += `];
?>`;
    
    // ecrire le fichier
    fs.writeFileSync('saisons_data.php', phpContent, 'utf8');
    
    // Le fichier JSON de debug n'est plus nécessaire
    
    return phpContent;
}

async function scraperSaisonsAstronomiques(anneeMin = null) {
    /**
     * scraper generique et evolutif pour recuperer toutes les dates d'equinoxes et solstices
     * disponibles sur icalendrier.fr, peu importe les annees presentes sur le site.
     * 
     * @param {number|null} anneeMin - annee minimum a recuperer. par defaut: annee courante
     * @returns {Object} donnees structurees par annee et saison
     */
    
    const url = "https://icalendrier.fr/outils/equinoxes-solstices";
    
    try {
        // annee minimum par defaut = annee courante
        if (anneeMin === null) {
            anneeMin = new Date().getFullYear();
        }
        
        console.log(`=== saisons astronomiques (>= ${anneeMin}) ===`);
        console.log("source: icalendrier.fr (institut de mecanique celeste et de calcul des ephemerides)");
        console.log(`url: ${url}`);
        console.log();
        
        // requete http avec timeout
        const response = await axios.get(url, { timeout: 10000 });
        const $ = cheerio.load(response.data);
        
        // mapping des saisons sans emojis
        const saisonsPatterns = {
            'printemps|spring': 'printemps',
            'ete|été|summer': 'ete',
            'automne|autumn|fall': 'automne',
            'hiver|winter': 'hiver'
        };
        
        // chercher toutes les tables
        const tables = $('table');
        console.log(`${tables.length} table(s) trouvee(s) sur la page`);
        
        // detecter automatiquement les annees disponibles
        const anneesDetectees = new Set();
        
        // chercher dans les titres
        $('h1, h2, h3, h4').each((i, heading) => {
            const text = $(heading).text();
            const matches = text.match(/\b(20\d{2})\b/g);
            if (matches) {
                matches.forEach(match => {
                    const year = parseInt(match);
                    if (year >= anneeMin) {
                        anneesDetectees.add(year);
                    }
                });
            }
        });
        
        // aussi chercher dans le contenu des tables
        tables.each((i, table) => {
            const text = $(table).text();
            const matches = text.match(/\b(20\d{2})\b/g);
            if (matches) {
                matches.forEach(match => {
                    const year = parseInt(match);
                    if (year >= anneeMin) {
                        anneesDetectees.add(year);
                    }
                });
            }
        });
        
        const anneesTriees = Array.from(anneesDetectees).sort();
        console.log(`annees detectees automatiquement: ${anneesTriees.join(', ')}`);
        console.log();
        
        // structure de donnees pour stocker les resultats
        const resultats = {};
        
        // analyser chaque table
        tables.each((i, table) => {
            const rows = $(table).find('tr');
            
            // filtrer les tables qui contiennent des donnees de saisons
            if (rows.length >= 3) {  // au moins 3 lignes (probablement des donnees)
                let anneeCourante = null;
                
                console.log(`table ${i + 1} - ${rows.length} ligne(s):`);
                
                rows.each((j, row) => {
                    const cells = $(row).find('td, th');
                    if (cells.length >= 2) {
                        const col1 = $(cells[0]).text().trim();
                        const col2 = $(cells[1]).text().trim();
                        
                        // detecter l'annee dans cette ligne
                        const yearMatch = col2.match(/\b(20\d{2})\b/);
                        if (yearMatch) {
                            anneeCourante = parseInt(yearMatch[1]);
                            if (anneeCourante >= anneeMin) {
                                if (!resultats[anneeCourante]) {
                                    resultats[anneeCourante] = {};
                                }
                            }
                        }
                        
                        // identifier la saison
                        let saisonIdentifiee = null;
                        for (const [pattern, nomSaison] of Object.entries(saisonsPatterns)) {
                            const regex = new RegExp(pattern, 'i');
                            if (regex.test(col1.toLowerCase())) {
                                saisonIdentifiee = nomSaison;
                                break;
                            }
                        }
                        
                        if (saisonIdentifiee && anneeCourante && anneeCourante >= anneeMin) {
                            // parser la date francaise au format iso
                            const dateIso = parserDateFrancaise(col2);
                            resultats[anneeCourante][saisonIdentifiee] = dateIso;
                            console.log(`  ${saisonIdentifiee.padEnd(15)}: ${dateIso}`);
                        } else if (col2 && /\d/.test(col2)) {  // ligne avec des donnees
                            console.log(`  ${col1.padEnd(15)}: ${col2}`);
                        }
                    }
                });
                console.log();
            }
        });
        
        console.log(`donnees recuperees pour ${Object.keys(resultats).length} annee(s)`);
        
        // si minYear est fourni et inferieur aux annees detectees, completer par des dates approximatives
        if (anneeMin !== null) {
            const yearsScraped = Object.keys(resultats).map(y => parseInt(y, 10));
            const earliest = yearsScraped.length > 0 ? Math.min(...yearsScraped) : null;
            if (earliest === null || anneeMin < earliest) {
                const startYear = anneeMin;
                const endYear = earliest ? (earliest - 1) : anneeMin; // si rien scrape, ne generer que min
                console.log(`ajout de saisons approximatives pour ${startYear}..${endYear}`);
                for (let y = startYear; y <= endYear; y++) {
                    if (!resultats[y]) resultats[y] = {};
                    // dates approximatives (fixes) par annee
                    resultats[y]['printemps'] = `${y}-03-20`;
                    resultats[y]['ete']       = `${y}-06-21`;
                    resultats[y]['automne']   = `${y}-09-22`;
                    resultats[y]['hiver']     = `${y}-12-21`;
                }
            }
        }

        // calculer debut et fin de chaque saison
        const resultatsAvecPeriodes = calculerPeriodesSeasons(resultats);
        
        // afficher les periodes completes
        console.log('\n=== periodes completes des saisons ===');
        for (const [annee, saisons] of Object.entries(resultatsAvecPeriodes)) {
            console.log(`\nannee ${annee}:`);
            for (const [saison, periode] of Object.entries(saisons)) {
                const duree = calculerDureeJours(periode.debut, periode.fin);
                console.log(`  ${saison.padEnd(15)}: ${periode.debut} -> ${periode.fin} (${duree} jours)`);
            }
        }
        
        // generer les donnees pour la base de donnees
        const donneesPHP = genererDonneesPHP(resultatsAvecPeriodes);
        console.log('\n=== donnees php generees ===');
        console.log('fichier saisons_data.php cree pour import automatique');
        
        return resultatsAvecPeriodes;
        
    } catch (error) {
        if (error.code === 'ECONNABORTED' || error.code === 'ETIMEDOUT') {
            console.log(`erreur de connexion: timeout`);
        } else {
            console.log(`erreur lors du scraping: ${error.message}`);
        }
        return {};
    }
}

// execution du script
async function main() {
    try {
        // parse CLI args: --min-year=YYYY ou -m YYYY
        let minYear = null;
        for (let i = 2; i < process.argv.length; i++) {
            const arg = process.argv[i];
            if (arg.startsWith('--min-year=')) {
                const val = parseInt(arg.split('=')[1], 10);
                if (!isNaN(val)) minYear = val;
            } else if ((arg === '--min-year' || arg === '-m') && i + 1 < process.argv.length) {
                const val = parseInt(process.argv[i + 1], 10);
                if (!isNaN(val)) minYear = val;
            }
        }

        // recuperer toutes les donnees disponibles (a partir de minYear si fourni)
        const donnees = await scraperSaisonsAstronomiques(minYear);
        console.log(`\nterminer - donnees recuperees pour ${Object.keys(donnees).length} annee(s)`);
    } catch (error) {
        console.log(`erreur dans main: ${error.message}`);
    }
}

// executer seulement si le fichier est lance directement
if (require.main === module) {
    main();
}

module.exports = { scraperSaisonsAstronomiques, parserDateFrancaise };