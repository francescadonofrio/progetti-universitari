/*Gestione di una libreria, i cui libri sono caratterizzati da:
-titolo 
-autore
-casa editrice
-prezzo 
-codice ISBN (stringa di 13 caratteri numerici presente sul retro di ogni libro) che funge da chiave di ricerca per ciascun libro.

Il programma permette di:
-visualizzare tutti i titoli presenti in libreria (o solo uno di essi)
-aggiungerne di nuovi o eliminarne di già presenti
-cambiare loro il prezzo 
-caricare una tabella di libri in/da un file esterno.*/

#include <stdio.h>
#include <stdlib.h>
#include <string.h>

#define MAXLUNG 50

struct nodoLista {
    char titolo[MAXLUNG];
    char autore[MAXLUNG];
    char casaEditrice[MAXLUNG];
    float prezzo;
    char codiceISBN[14];
    struct nodoLista *nextLibro;
};

typedef struct nodoLista tNodo;
typedef tNodo *tLista;

void stampaLista(tLista);
void stampaNodo(tNodo *);
void stampaLibro(tNodo *);
int  leggiLibroDaInput(tNodo *);
int  insTestaLista(tLista *, tNodo *);
int  cancellaLibro(tLista *, const char *);
int  modificaPrezzoLibro(tLista, const char *, float);
tNodo *trovaLibroPerIsbn(tLista, const char *);
int  leggiLibri(tLista *, char *);
int  memorizzaLibri(tLista, char *);

int main() {
    tLista unaLista = NULL;
    tNodo libroAppoggio;
    int scelta, ok;
    char nomefile[128];
    char isbn[14];
    float nuovoPrezzo;

    do {
        printf("\nSelezionare azione:\n");
        printf("1. stampa dei titoli disponibili\n");
        printf("2. stampa di un titolo\n");
        printf("3. aggiunta di un titolo\n");
        printf("4. eliminazione di un titolo\n");
        printf("5. modifica prezzo libro\n");
        printf("6. tabella --> filedati\n");
        printf("7. filedati --> tabella\n");
        printf("0. FINE\n\n");

        if (scanf("%d", &scelta) != 1) {
            printf("ERRORE.\n\n");
            return 0;
        }
        getchar();

        switch (scelta) {
            case 1:
                stampaLista(unaLista);
                break;

            case 2: {
                tNodo *n;
                size_t L;

                printf("Inserisci il codice ISBN del libro: ");
                if (fgets(isbn, sizeof(isbn), stdin) == NULL) {
                    printf("ERRORE.\n\n");
                    break;
                }

                L = strlen(isbn);
                while (L > 0 && (isbn[L-1] == '\n' || isbn[L-1] == '\r')) {
                    isbn[--L] = '\0';
                }

                n = trovaLibroPerIsbn(unaLista, isbn);
                if (n) {
                    stampaLibro(n);
                } else {
                    printf("ERRORE.\n\n");
                }
                break;
            }

            case 3:
                ok = leggiLibroDaInput(&libroAppoggio);
                if (!ok) {
                    printf("ERRORE.\n\n");
                    break;
                }

                ok = insTestaLista(&unaLista, &libroAppoggio);
                if (ok) {
                    printf("Azione avvenuta con successo.\n\n");
                } else {
                    printf("ERRORE.\n\n");
                }
                break;

            case 4: {
                size_t L;

                printf("Inserisci il codice ISBN del libro che vuoi cancellare: ");
                if (fgets(isbn, sizeof(isbn), stdin) == NULL) {
                    printf("ERRORE.\n\n");
                    break;
                }

                L = strlen(isbn);
                while (L > 0 && (isbn[L-1] == '\n' || isbn[L-1] == '\r')) {
                    isbn[--L] = '\0';
                }

                ok = cancellaLibro(&unaLista, isbn);
                if (ok) {
                    printf("Azione avvenuta con successo.\n\n");
                } else {
                    printf("ERRORE.\n\n");
                }
                break;
            }

            case 5: {
                size_t L;

                printf("Inserisci l'ISBN del libro: ");
                if (fgets(isbn, sizeof(isbn), stdin) == NULL) {
                    printf("ERRORE.\n\n");
                    break;
                }

                L = strlen(isbn);
                while (L > 0 && (isbn[L-1] == '\n' || isbn[L-1] == '\r')) {
                    isbn[--L] = '\0';
                }

                printf("Inserisci il nuovo prezzo: ");
                if (scanf("%f", &nuovoPrezzo) != 1) {
                    printf("ERRORE.\n\n");
                    return 0;
                }
                getchar();

                ok = modificaPrezzoLibro(unaLista, isbn, nuovoPrezzo);
                if (ok) {
                    printf("Azione avvenuta con successo.\n\n");
                } else {
                    printf("ERRORE.\n\n");
                }
                break;
            }

            case 6: {
                size_t L;

                printf("Digitare il nome del file in cui salvare la tabella: ");
                if (fgets(nomefile, sizeof(nomefile), stdin) == NULL) {
                    printf("ERRORE.\n\n");
                    break;
                }

                L = strlen(nomefile);
                while (L > 0 && (nomefile[L-1] == '\n' || nomefile[L-1] == '\r')) {
                    nomefile[--L] = '\0';
                }

                ok = memorizzaLibri(unaLista, nomefile);
                if (ok) {
                    printf("Azione avvenuta con successo.\n\n");
                } else {
                    printf("ERRORE.\n\n");
                }
                break;
            }

            case 7: {
                size_t L;

                printf("Digitare il nome del file da cui caricare i dati nella tabella: ");
                if (fgets(nomefile, sizeof(nomefile), stdin) == NULL) {
                    printf("ERRORE.\n\n");
                    break;
                }

                L = strlen(nomefile);
                while (L > 0 && (nomefile[L-1] == '\n' || nomefile[L-1] == '\r')) {
                    nomefile[--L] = '\0';
                }

                ok = leggiLibri(&unaLista, nomefile);
                if (ok) {
                    printf("Azione avvenuta con successo.\n\n");
                } else {
                    printf("ERRORE.\n\n");
                }
                break;
            }

            case 0:
                printf("FINE\n");
                break;

            default:
                printf("ERRORE.\n\n");
        }
    } while (scelta != 0);

    return 0;
}

void stampaLista(tLista laLista) {
    printf("\nLista dei libri:\n");
    printf("%s, %s, %s, %s, %s\n\n",
           "Titolo", "Autore", "Casa Editrice", "Codice ISBN", "Prezzo");

    while (laLista) {
        stampaNodo(laLista);
        laLista = laLista->nextLibro;
    }
}

void stampaNodo(tNodo *p) {
    printf("%s, %s, %s, %s, %.2f\n",
           p->titolo,
           p->autore,
           p->casaEditrice,
           p->codiceISBN,
           p->prezzo);
}

void stampaLibro(tNodo *l) {
    printf("Titolo: %s\n", l->titolo);
    printf("Autore: %s\n", l->autore);
    printf("Casa Editrice: %s\n", l->casaEditrice);
    printf("Prezzo: %.2f\n", l->prezzo);
    printf("ISBN: %s\n", l->codiceISBN);
}

int leggiLibroDaInput(tNodo *p) {
    size_t L;

    printf("\nTitolo del libro: ");
    if (fgets(p->titolo, MAXLUNG, stdin) == NULL) {
        return 0;
    }
    L = strlen(p->titolo);
    while (L > 0 && (p->titolo[L-1] == '\n' || p->titolo[L-1] == '\r')) {
        p->titolo[--L] = '\0';
    }

    printf("Autore del libro: ");
    if (fgets(p->autore, MAXLUNG, stdin) == NULL) {
        return 0;
    }
    L = strlen(p->autore);
    while (L > 0 && (p->autore[L-1] == '\n' || p->autore[L-1] == '\r')) {
        p->autore[--L] = '\0';
    }

    printf("Casa editrice: ");
    if (fgets(p->casaEditrice, MAXLUNG, stdin) == NULL) {
        return 0;
    }
    L = strlen(p->casaEditrice);
    while (L > 0 && (p->casaEditrice[L-1] == '\n' || p->casaEditrice[L-1] == '\r')) {
        p->casaEditrice[--L] = '\0';
    }

    printf("Prezzo: ");
    if (scanf("%f", &p->prezzo) != 1) {
        return 0;
    }
    getchar();

    printf("Codice ISBN: ");
    if (fgets(p->codiceISBN, 14, stdin) == NULL) {
        return 0;
    }
    L = strlen(p->codiceISBN);
    while (L > 0 && (p->codiceISBN[L-1] == '\n' || p->codiceISBN[L-1] == '\r')) {
        p->codiceISBN[--L] = '\0';
    }

    return 1;
}

int insTestaLista(tLista *head, tNodo *src) {
    tLista n = malloc(sizeof(tNodo)); 
    if (!n) {
        return 0;
    }

    *n = *src;
    n->nextLibro = *head;
    *head = n;
    return 1;
}

int cancellaLibro(tLista *head, const char *isbn) {
    tLista cur = *head;
    tLista prev = NULL;

    while (cur && strcmp(cur->codiceISBN, isbn) != 0) {
        prev = cur;
        cur = cur->nextLibro;
    }

    if (!cur) {
        return 0;
    }

    if (prev) {
        prev->nextLibro = cur->nextLibro;
    } else {
        *head = cur->nextLibro;
    }

    free(cur);
    return 1;
}

int modificaPrezzoLibro(tLista l, const char *isbn, float nuovoPrezzo) {
    while (l) {
        if (strcmp(l->codiceISBN, isbn) == 0) {
            l->prezzo = nuovoPrezzo;
            return 1;
        }
        l = l->nextLibro;
    }
    return 0;
}

tNodo *trovaLibroPerIsbn(tLista l, const char *isbn) {
    while (l) {
        if (strcmp(l->codiceISBN, isbn) == 0) {
            return l;
        }
        l = l->nextLibro;
    }
    return NULL;
}

int leggiLibri(tLista *head, char *nomeFile) {
    FILE *fp = fopen(nomeFile, "r");
    char riga[256];
    int n, i;
    tNodo libro;
    size_t L;

    if (fp == NULL) {
        return 0;
    }

    if (fgets(riga, sizeof(riga), fp) == NULL) {
        fclose(fp);
        return 0;
    }

    L = strlen(riga);
    while (L > 0 && (riga[L-1] == '\n' || riga[L-1] == '\r')) {
        riga[--L] = '\0';
    }

    if (sscanf(riga, "%d", &n) != 1 || n < 0) {
        fclose(fp);
        return 0;
    }

    for (i = 0; i < n; i++) {
        if (fgets(riga, sizeof(riga), fp) == NULL) {
            fclose(fp);
            return 0;
        }
        L = strlen(riga);
        while (L > 0 && (riga[L-1] == '\n' || riga[L-1] == '\r')) {
            riga[--L] = '\0';
        }
        strncpy(libro.titolo, riga, MAXLUNG-1);
        libro.titolo[MAXLUNG-1] = '\0';

        if (fgets(riga, sizeof(riga), fp) == NULL) {
            fclose(fp);
            return 0;
        }
        L = strlen(riga);
        while (L > 0 && (riga[L-1] == '\n' || riga[L-1] == '\r')) {
            riga[--L] = '\0';
        }
        strncpy(libro.autore, riga, MAXLUNG-1);
        libro.autore[MAXLUNG-1] = '\0';

        if (fgets(riga, sizeof(riga), fp) == NULL) {
            fclose(fp);
            return 0;
        }
        L = strlen(riga);
        while (L > 0 && (riga[L-1] == '\n' || riga[L-1] == '\r')) {
            riga[--L] = '\0';
        }
        strncpy(libro.casaEditrice, riga, MAXLUNG-1);
        libro.casaEditrice[MAXLUNG-1] = '\0';

        if (fgets(riga, sizeof(riga), fp) == NULL) {
            fclose(fp);
            return 0;
        }
        if (sscanf(riga, "%f %13s", &libro.prezzo, libro.codiceISBN) != 2) {
            fclose(fp);
            return 0;
        }

        if (!insTestaLista(head, &libro)) {
            fclose(fp);
            return 0;
        }
    }

    fclose(fp);
    return 1;
}

int memorizzaLibri(tLista l, char *nomeFile) {
    FILE *fp = fopen(nomeFile, "w");
    int count = 0;
    tLista tmp = l;

    if (!fp) {
        return 0;
    }

    while (tmp) {
        count++;
        tmp = tmp->nextLibro;
    }
    fprintf(fp, "%d\n", count);

    while (l) {
        fprintf(fp, "%s\n", l->titolo);
        fprintf(fp, "%s\n", l->autore);
        fprintf(fp, "%s\n", l->casaEditrice);
        fprintf(fp, "%.2f %s\n", l->prezzo, l->codiceISBN);
        l = l->nextLibro;
    }

    fclose(fp);
    return 1;
}

