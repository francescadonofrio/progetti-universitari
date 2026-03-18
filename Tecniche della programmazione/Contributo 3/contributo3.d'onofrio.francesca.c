/*Gestione di un archivio di ISBN (codici numerici a 13 cifre) organizzato in un albero binario di ricerca.

Ogni nodo dell'albero contiene un unico ISBN.
L'albero è ordinato in base al valore dell'ISBN: se l'ISBN inserito è minore di quello del nodo corrente, si scende a sinistra; se è maggiore si scende a destra; se è uguale non viene inserito. 

Il programma permette di:
-stampare l'albero in forma parentetica, in particolare in preordine/postordine/simmetria
-inserire/eliminare/modificare (eliminazione+inserimento) un nuovo ISBN nell'albero
-cercare un ISBN nell'albero
-caricare un albero da/su un file.*/

#include <stdio.h>
#include <stdlib.h>
#include <string.h>

typedef struct Nodo {
    char isbn[14];        
    struct Nodo *sin, *des;
} Nodo;

typedef Nodo* Albero;

void stampaAlbero(Albero a);
void stampaPreordine(Albero a);
void stampaPostordine(Albero a);
void stampaSimmetrica(Albero a);

Albero inserisciISBN(Albero a, char isbn[]);
Albero eliminaISBN(Albero a, char isbn[]);
Nodo* cercaISBN(Albero a, char isbn[]);
Albero modificaISBN(Albero a, char vecchio[], char nuovo[]);

void deallocaAlbero(Albero* a);
int caricaDaFile(Albero *a, char nomeFile[]);
int salvaSuFile(Albero a, char nomeFile[]);

int main() {
    Albero albero = NULL;
    int scelta;
    char isbn[14], nuovo[14], nomeFile[50];
    Nodo* trovato;

    do {
        printf("\nSelezionare azione:\n");
        printf("1. Stampa albero in forma parentetica\n");
        printf("2. Stampa albero in preordine\n");
        printf("3. Stampa albero in postordine\n");
        printf("4. Stampa albero in forma simmetrica (ordinata)\n");
        printf("5. Inserisci ISBN\n");
        printf("6. Elimina ISBN\n");
        printf("7. Cerca ISBN\n");
        printf("8. Modifica ISBN\n");
        printf("9. Carica albero da file\n");
        printf("10. Salva albero su file\n");
        printf("0. Esci\n\n");
        scanf("%d", &scelta);
        getchar();

        switch (scelta) {
            case 1:
                stampaAlbero(albero); printf("\n"); break;
            case 2:
                stampaPreordine(albero); break;
            case 3:
                stampaPostordine(albero); break;
            case 4:
                stampaSimmetrica(albero); break;
            case 5:
                printf("ISBN da inserire: ");
                fgets(isbn, 14, stdin); isbn[strcspn(isbn, "\n")] = 0;
                albero = inserisciISBN(albero, isbn);
                break;
            case 6:
                printf("ISBN da eliminare: ");
                fgets(isbn, 14, stdin); isbn[strcspn(isbn, "\n")] = 0;
                albero = eliminaISBN(albero, isbn);
                break;
            case 7:
                printf("ISBN da cercare: ");
                fgets(isbn, 14, stdin); isbn[strcspn(isbn, "\n")] = 0;
                trovato = cercaISBN(albero, isbn);
                if (trovato) printf("Trovato ISBN: %s\n", trovato->isbn);
                else printf("ISBN non trovato.\n");
                break;
            case 8:
                printf("ISBN da modificare: ");
                fgets(isbn, 14, stdin); isbn[strcspn(isbn, "\n")] = 0;
                printf("Nuovo ISBN: ");
                fgets(nuovo, 14, stdin); nuovo[strcspn(nuovo, "\n")] = 0;
                albero = modificaISBN(albero, isbn, nuovo);
                break;
            case 9:
                printf("Nome file da cui caricare: ");
                fgets(nomeFile, 50, stdin); nomeFile[strcspn(nomeFile, "\n")] = 0;
                if (caricaDaFile(&albero, nomeFile)) printf("Caricamento riuscito.\n");
                else printf("Errore nel caricamento.\n");
                break;
            case 10:
                printf("Nome file su cui salvare: ");
                fgets(nomeFile, 50, stdin); nomeFile[strcspn(nomeFile, "\n")] = 0;
                if (salvaSuFile(albero, nomeFile)) printf("Salvataggio riuscito.\n");
                else printf("Errore nel salvataggio.\n");
                break;
            case 0:
                deallocaAlbero(&albero);
                printf("FINE.\n"); break;
            default:
                printf("Scelta non valida.\n");
        }
    } while (scelta != 0);

    return 0;
}

void stampaAlbero(Albero a) {
    if (a == NULL) printf("()");
    else {
        printf("( %s ", a->isbn);
        stampaAlbero(a->sin);
        printf(" ");
        stampaAlbero(a->des);
        printf(" )");
    }
}

void stampaPreordine(Albero a) {
    if (a != NULL) {
        printf("- %s\n", a->isbn);
        stampaPreordine(a->sin);
        stampaPreordine(a->des);
    }
}

void stampaPostordine(Albero a) {
    if (a != NULL) {
        stampaPostordine(a->sin);
        stampaPostordine(a->des);
        printf("- %s\n", a->isbn);
    }
}

void stampaSimmetrica(Albero a) {
    if (a != NULL) {
        stampaSimmetrica(a->sin);
        printf("- %s\n", a->isbn);
        stampaSimmetrica(a->des);
    }
}

Albero inserisciISBN(Albero a, char isbn[]) {
    if (a == NULL) {
        Albero nuovo = (Albero)malloc(sizeof(Nodo));
        strcpy(nuovo->isbn, isbn);
        nuovo->sin = nuovo->des = NULL;
        return nuovo;
    }
    int cmp = strcmp(isbn, a->isbn);
    if (cmp < 0) a->sin = inserisciISBN(a->sin, isbn);
    else if (cmp > 0) a->des = inserisciISBN(a->des, isbn);
    return a;
}

Albero eliminaISBN(Albero a, char isbn[]) {
    if (a == NULL) return NULL;
    int cmp = strcmp(isbn, a->isbn);
    if (cmp < 0) a->sin = eliminaISBN(a->sin, isbn);
    else if (cmp > 0) a->des = eliminaISBN(a->des, isbn);
    else {
        if (a->sin == NULL) {
            Albero temp = a->des; free(a); return temp;
        } else if (a->des == NULL) {
            Albero temp = a->sin; free(a); return temp;
        } else {
            Albero succ = a->des;
            while (succ->sin != NULL) succ = succ->sin;
            strcpy(a->isbn, succ->isbn);
            a->des = eliminaISBN(a->des, succ->isbn);
        }
    }
    return a;
}

Nodo* cercaISBN(Albero a, char isbn[]) {
    if (a == NULL) return NULL;
    int cmp = strcmp(isbn, a->isbn);
    if (cmp < 0) return cercaISBN(a->sin, isbn);
    else if (cmp > 0) return cercaISBN(a->des, isbn);
    else return a;
}

Albero modificaISBN(Albero a, char vecchio[], char nuovo[]) {
    if (cercaISBN(a, vecchio) == NULL) {
        printf("ISBN non trovato.\n");
        return a;
    }
    a = eliminaISBN(a, vecchio);
    a = inserisciISBN(a, nuovo);
    printf("ISBN modificato con successo.\n");
    return a;
}

void deallocaAlbero(Albero* a) {
    if (*a != NULL) {
        deallocaAlbero(&(*a)->sin);
        deallocaAlbero(&(*a)->des);
        free(*a);
        *a = NULL;
    }
}

int caricaDaFile(Albero *a, char nomeFile[]) {
    FILE *fp = fopen(nomeFile, "r");
    if (!fp) return 0;

    deallocaAlbero(a);

    int n, i;
    char isbn[14];
    if (fscanf(fp, "%d\n", &n) != 1) { fclose(fp); return 0; }

    for (i = 0; i < n; i++) {
        if (fgets(isbn, sizeof(isbn), fp) == NULL) { fclose(fp); return 0; }
        isbn[strcspn(isbn, "\n")] = 0;
        *a = inserisciISBN(*a, isbn);
    }
    fclose(fp);
    return 1;
}

int salvaSuFile(Albero a, char nomeFile[]) {
    FILE *fp = fopen(nomeFile, "w");
    if (!fp) return 0;

    int count = 0;
    void conta(Albero t) { if (t) { count++; conta(t->sin); conta(t->des); } }
    conta(a);
    fprintf(fp, "%d\n", count);

    void salva(Albero t) {
        if (t) {
            salva(t->sin);
            fprintf(fp, "%s\n", t->isbn);
            salva(t->des);
        }
    }
    salva(a);

    fclose(fp);
    return 1;
}

