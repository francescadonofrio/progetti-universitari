/*Gestione di una libreria, i cui libri sono caratterizzati da:
-titolo 
-autore
-casa editrice
-prezzo 
-codice Isbn (stringa di 13 caratteri numerici presente sul retro di ogni libro) che funge da chiave di ricerca per ciascun libro.

Il programma permette di:
-visualizzare tutti i titoli presenti in libreria (o solo uno di essi)
-aggiungerne di nuovi o eliminarne di già presenti
-cambiare loro il prezzo 
-caricare una tabella di libri in/da un file esterno.*/

#include <stdio.h>
#include <string.h>
#include <stdlib.h>
#include <ctype.h>

#define MAXLIBRI 10000
#define MAXLUNG 50
#define NOMEFILEAPPOGGIO "TEMP.DAT"
#define RATIO 30

struct libro {
    char titolo[MAXLUNG];
    char autore[MAXLUNG];
    char casaEditrice[MAXLUNG];
    float prezzo;
    char codiceIsbn[14];  
};

typedef struct libro TipoLibro;

typedef struct {
    TipoLibro* arrayLibri;
    int quantiLibri;
    int libriAllocati;
} TipoTabella;

void stampaTabella(TipoTabella t);
void stampaQuelLibro(TipoTabella t, char codiceIsbn[]);
int aggiungiLibro(TipoTabella* t);
int eliminaLibro(TipoTabella* t, char codiceIsbn[]);
int cambiaPrezzo(TipoTabella* t, char codiceIsbn[], float nuovoPrezzo);
void daTabellaInFile(TipoTabella t, char* nmf);                    
int daFileInTabella2(char* nmf, TipoTabella* t, double extraDim);  
int indiceLibro(TipoTabella t, char codiceIsbn[]);

void pulisciBuffer(void);
void leggiLinea(char *dest, int max);
void trimFineRiga(char *s);

int main() {
    TipoTabella tabLibri;
    int scelta;
    char buffer[MAXLUNG];
    char isbn[14];
    float nuovoPrezzo;

    tabLibri.arrayLibri = malloc(MAXLIBRI * sizeof(TipoLibro));
    if (tabLibri.arrayLibri == NULL) {
        printf("ERRORE\n");
        return 0;
    }
    tabLibri.quantiLibri = 0;
    tabLibri.libriAllocati = MAXLIBRI;

    do {
        printf("Selezionare azione:\n");
        printf("1. stampa dei titoli disponibili\n");
        printf("2. stampa di un titolo\n");
        printf("3. aggiunta di un titolo\n");
        printf("4. eliminazione di un titolo\n");
        printf("5. modifica prezzo libro\n");
        printf("6. tabella --> filedati\n");
        printf("7. filedati --> tabella\n");
        printf("0. FINE\n\n\n");

        if (scanf("%d", &scelta) != 1) { printf("ERRORE\n"); return 0; }
        pulisciBuffer();

        switch (scelta) {
            case 1:
                printf("%d titoli disponibili.\n\n", tabLibri.quantiLibri);
                stampaTabella(tabLibri);
                break;

            case 2:
                printf("Digitare il codice ISBN del libro da ricercare: ");
                leggiLinea(buffer, MAXLUNG);
                stampaQuelLibro(tabLibri, buffer);
                putchar('\n');
                break;

            case 3:
                if (aggiungiLibro(&tabLibri))
                    printf("Azione eseguita con successo.\n\n");
                else
                    printf("ERRORE\n");
                break;

            case 4:
                printf("Digitare il codice ISBN del libro da eliminare: ");
                leggiLinea(buffer, MAXLUNG);
                if (eliminaLibro(&tabLibri, buffer))
                    printf("Azione eseguita con successo.\n\n");
                else
                    printf("ERRORE\n");
                break;

            case 5:
                printf("Codice ISBN del libro da modificare: ");
                leggiLinea(isbn, 14);
                printf("Nuovo prezzo: ");
                if (scanf("%f", &nuovoPrezzo) != 1) { printf("ERRORE\n"); return 0; }
                pulisciBuffer();
                if (cambiaPrezzo(&tabLibri, isbn, nuovoPrezzo))
                    printf("Azione eseguita con successo.\n\n");
                else
                    printf("ERRORE\n");
                break;

            case 6:
    			printf("Digitare il nome del file in cui salvare la tabella: ");
    			leggiLinea(buffer, MAXLUNG);
    			daTabellaInFile(tabLibri, buffer);
    			printf("Azione eseguita con successo.\n\n");
    			break;


            case 7:
                printf("Digitare il nome del file da cui caricare i dati nella tabella: ");
                leggiLinea(buffer, MAXLUNG);
                if (daFileInTabella2(buffer, &tabLibri, RATIO))
                    printf("Azione eseguita con successo.\n\n");
                else
                    printf("ERRORE\n");
                break;

            case 0:
                printf("\nFINE\n");
                break;

            default:
                printf("ERRORE\n");
        }
    } while (scelta != 0);

    printf("\nFINE\n");
    free(tabLibri.arrayLibri);
    return 0;
}

int indiceLibro(TipoTabella t, char codiceIsbn[]) {
    int i;
    for (i = 0; i < t.quantiLibri; i++) {
        if (strcmp(t.arrayLibri[i].codiceIsbn, codiceIsbn) == 0) {
            return i;
        }
    }
    return -1;
}

void stampaTabella(TipoTabella t) {
    int i;
    printf("\nLibri disponibili:\n\n");
    for (i = 0; i < t.quantiLibri; i++) {
        TipoLibro libro = t.arrayLibri[i];
        printf("Titolo: %s\n", libro.titolo);
        printf("Autore: %s\n", libro.autore);
        printf("Casa Editrice: %s\n", libro.casaEditrice);
        printf("Prezzo: %.2f\n", libro.prezzo);
        printf("Codice ISBN: %s\n\n", libro.codiceIsbn);
    }
}

void stampaQuelLibro(TipoTabella t, char codiceIsbn[]) {
    int k;
    k = indiceLibro(t, codiceIsbn);

    if (k == -1) {
        printf("\nTitolo non trovato\n");
    } else {
        TipoLibro libro = t.arrayLibri[k];
        printf("Titolo: %s\n", libro.titolo);
        printf("Autore: %s\n", libro.autore);
        printf("Casa Editrice: %s\n", libro.casaEditrice);
        printf("Prezzo: %.2f\n", libro.prezzo);
        printf("Codice ISBN: %s\n", libro.codiceIsbn);
    }
}

int aggiungiLibro(TipoTabella* t) {
    TipoLibro nuovoLibro;

    if (t->quantiLibri == t->libriAllocati) {
        daTabellaInFile(*t, NOMEFILEAPPOGGIO);
        if (!daFileInTabella2(NOMEFILEAPPOGGIO, t, RATIO)) {
            printf("ERRORE\n");
            return 0;
        }
    }

    printf("Titolo: ");
    leggiLinea(nuovoLibro.titolo, MAXLUNG);
    printf("Autore: ");
    leggiLinea(nuovoLibro.autore, MAXLUNG);
    printf("Casa editrice: ");
    leggiLinea(nuovoLibro.casaEditrice, MAXLUNG);
    printf("Prezzo: ");
    if (scanf("%f", &nuovoLibro.prezzo) != 1) { printf("ERRORE\n"); return 0; }
    pulisciBuffer();
    printf("Codice ISBN (13 numeri): ");
    leggiLinea(nuovoLibro.codiceIsbn, 14);

    t->arrayLibri[t->quantiLibri] = nuovoLibro;
    t->quantiLibri++;
    return 1;
}

int eliminaLibro(TipoTabella* t, char codiceIsbn[]) {
    int k, ultimo;

    k = indiceLibro(*t, codiceIsbn);
    ultimo = t->quantiLibri - 1;

    if (k == -1) {
        printf("\nTitolo non trovato\n");
        return 0;
    } else {
        t->arrayLibri[k] = t->arrayLibri[ultimo];
        t->quantiLibri--;
        return 1;
    }
}

int cambiaPrezzo(TipoTabella* t, char codiceIsbn[], float nuovoPrezzo) {
    int k;
    k = indiceLibro(*t, codiceIsbn);

    if (k == -1) {
        printf("\nTitolo non trovato\n");
        return 0;
    } else {
        t->arrayLibri[k].prezzo = nuovoPrezzo;
        return 1;
    }
}

void daTabellaInFile (TipoTabella t, char *nmf) {
    FILE *f;
    int i;

    f = fopen(nmf, "w");
    if (f == NULL) {
        printf("Impossibile aprire il file.\n");
    } else {
        fprintf(f, "%d\n", t.quantiLibri);
        for (i = 0; i < t.quantiLibri; i++) {
            fprintf(f, "%s\n",  t.arrayLibri[i].titolo);
            fprintf(f, "%s\n",  t.arrayLibri[i].autore);
            fprintf(f, "%s\n",  t.arrayLibri[i].casaEditrice);
            fprintf(f, "%.2f %s\n", t.arrayLibri[i].prezzo, t.arrayLibri[i].codiceIsbn);
        }
        fclose(f);
    }
}

int daFileInTabella2 (char *nmf, TipoTabella *t, double extraDim) {
    FILE *f;
    char line[256];
    int n, i;
    TipoLibro* p;

    f = fopen(nmf, "r");
    if (f == NULL) {
        printf("Impossibile aprire il file.\n");
        return 0;
    }

    do {
        if (fgets(line, sizeof(line), f) == NULL) { printf("Formato file non valido.\n"); fclose(f); return 0; }
        trimFineRiga(line);
    } while (line[0] == '\0');

    if (sscanf(line, "%d", &n) != 1 || n < 0) {
        printf("Formato file non valido.\n");
        fclose(f);
        return 0;
    }

    p = realloc(t->arrayLibri, (int)(n * (1 + extraDim / 100)) * sizeof(TipoLibro));
    if (p == NULL) {
        printf("Errore durante la riallocazione della memoria.\n");
        fclose(f);
        return 0;
    }
    t->arrayLibri    = p;
    t->libriAllocati = (int)(n * (1 + extraDim / 100));
    t->quantiLibri   = n;

    for (i = 0; i < n; i++) {
       
        if (fgets(line, sizeof(line), f) == NULL) { printf("Formato file non valido.\n"); fclose(f); return 0; }
        trimFineRiga(line);
        strncpy(t->arrayLibri[i].titolo, line, MAXLUNG-1); t->arrayLibri[i].titolo[MAXLUNG-1] = '\0';

        if (fgets(line, sizeof(line), f) == NULL) { printf("Formato file non valido.\n"); fclose(f); return 0; }
        trimFineRiga(line);
        strncpy(t->arrayLibri[i].autore, line, MAXLUNG-1); t->arrayLibri[i].autore[MAXLUNG-1] = '\0';

        if (fgets(line, sizeof(line), f) == NULL) { printf("Formato file non valido.\n"); fclose(f); return 0; }
        trimFineRiga(line);
        strncpy(t->arrayLibri[i].casaEditrice, line, MAXLUNG-1); t->arrayLibri[i].casaEditrice[MAXLUNG-1] = '\0';

        if (fgets(line, sizeof(line), f) == NULL) { printf("Formato file non valido.\n"); fclose(f); return 0; }
        trimFineRiga(line);
        if (sscanf(line, "%f %13s", &t->arrayLibri[i].prezzo, t->arrayLibri[i].codiceIsbn) != 2) {
            printf("Formato prezzo/ISBN non valido alla riga %d.\n", i+1);
            fclose(f);
            return 0;
        }
    }

    fclose(f);
    return 1;
}

void pulisciBuffer(void) {
    int c;
    while ((c = getchar()) != '\n' && c != EOF) { }
}

void trimFineRiga(char *s) {
    int n;
    n = (int)strlen(s);
    while (n > 0 && (s[n-1] == '\n' || s[n-1] == '\r')) {
        s[n-1] = '\0';
        n--;
    }
}

void leggiLinea(char *dest, int max) {
    if (fgets(dest, max, stdin) == NULL) { dest[0] = '\0'; return; }
    trimFineRiga(dest);
}

